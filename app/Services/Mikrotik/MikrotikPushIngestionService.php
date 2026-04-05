<?php

namespace App\Services\Mikrotik;

use App\Models\Isp;
use App\Models\IspHealthSnapshot;
use App\Models\IspSnapshot;
use App\Models\MonitoredUser;
use App\Models\UserSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MikrotikPushIngestionService
{
    public function __construct(
        protected readonly CounterDeltaCalculator $counterDeltaCalculator,
    ) {
    }

    public function logAccessAttempt(Request $request): void
    {
        $queues = is_array($request->input('queues')) ? $request->input('queues') : [];
        $interfaces = is_array($request->input('interfaces')) ? $request->input('interfaces') : [];
        $healthItems = is_array($request->input('health')) ? $request->input('health') : [];

        Log::info('MikroTik push endpoint accessed.', [
            'ip' => $request->ip(),
            'router_name' => $request->input('router_name'),
            'sent_at' => $request->input('sent_at'),
            'queue_count' => count($queues),
            'interface_count' => count($interfaces),
            'health_count' => count($healthItems),
            'queue_names' => collect($queues)->pluck('name')->filter()->values()->all(),
            'interface_names' => collect($interfaces)->pluck('name')->filter()->values()->all(),
            'health_names' => collect($healthItems)->pluck('name')->filter()->values()->all(),
            'has_query_token' => $request->query->has('token'),
            'has_header_token' => $request->headers->has('X-Mikrotik-Token'),
        ]);

        if ($interfaces !== [] || $healthItems !== []) {
            Log::info('MikroTik push payload details.', [
                'interfaces' => collect($interfaces)->map(fn (array $item): array => [
                    'name' => $item['name'] ?? null,
                    'rx_bytes' => $item['rx_bytes'] ?? null,
                    'tx_bytes' => $item['tx_bytes'] ?? null,
                ])->values()->all(),
                'health' => collect($healthItems)->map(fn (array $item): array => [
                    'name' => $item['name'] ?? null,
                    'ping_target' => $item['ping_target'] ?? null,
                    'latency_ms' => $item['latency_ms'] ?? null,
                    'packet_loss_percent' => $item['packet_loss_percent'] ?? null,
                    'jitter_ms' => $item['jitter_ms'] ?? null,
                    'status' => $item['status'] ?? null,
                ])->values()->all(),
            ]);
        }
    }

    public function hasValidToken(Request $request): bool
    {
        $configuredToken = (string) config('mikrotik.push_token', '');
        $providedToken = (string) ($request->query('token') ?? $request->header('X-Mikrotik-Token') ?? '');

        return $configuredToken !== ''
            && $providedToken !== ''
            && hash_equals($configuredToken, $providedToken);
    }

    public function logUnauthorizedAttempt(Request $request): void
    {
        Log::warning('Unauthorized MikroTik push attempt.', [
            'ip' => $request->ip(),
            'router_name' => $request->input('router_name'),
            'has_query_token' => $request->query->has('token'),
            'has_header_token' => $request->headers->has('X-Mikrotik-Token'),
        ]);
    }

    public function ingest(array $payload): array
    {
        $recordedAt = isset($payload['sent_at'])
            ? CarbonImmutable::parse($payload['sent_at'])
            : CarbonImmutable::now();

        $routerName = $payload['router_name'] ?? null;
        $skippedQueues = [];
        $queuesIngested = 0;
        $interfacesIngested = 0;
        $healthIngested = 0;

        DB::transaction(function () use (
            $payload,
            $recordedAt,
            $routerName,
            &$skippedQueues,
            &$queuesIngested,
            &$interfacesIngested,
            &$healthIngested,
        ): void {
            $queues = $payload['queues'] ?? [];
            $interfaces = $payload['interfaces'] ?? [];
            $healthItems = $payload['health'] ?? [];

            $userMap = MonitoredUser::query()
                ->where('is_active', true)
                ->whereIn('queue_name', collect($queues)->pluck('name')->all())
                ->get()
                ->keyBy('queue_name');

            foreach ($queues as $queue) {
                $queueName = $queue['name'];

                if (in_array($queueName, config('mikrotik.excluded_queue_names', []), true)) {
                    $skippedQueues[] = $queueName;
                    continue;
                }

                $user = $userMap->get($queueName);

                if (! $user) {
                    Log::warning('Unknown MikroTik queue received via push ingestion.', [
                        'router_name' => $routerName,
                        'queue_name' => $queueName,
                    ]);

                    continue;
                }

                $uploadBytes = (int) $queue['upload_bytes'];
                $downloadBytes = (int) $queue['download_bytes'];
                $maxLimit = $queue['max_limit'] ?? null;

                UserSnapshot::query()->create([
                    'monitored_user_id' => $user->id,
                    'upload_bytes_total' => $uploadBytes,
                    'download_bytes_total' => $downloadBytes,
                    'total_bytes' => $uploadBytes + $downloadBytes,
                    'max_limit' => $maxLimit,
                    'state' => $maxLimit === $user->throttled_max_limit ? 'THROTTLED' : 'NORMAL',
                    'recorded_at' => $recordedAt,
                ]);

                $queuesIngested++;
            }

            $ispMap = Isp::query()
                ->where('is_active', true)
                ->whereIn('interface_name', collect($interfaces)->pluck('name')->all())
                ->get()
                ->keyBy('interface_name');

            foreach ($interfaces as $interface) {
                $interfaceName = $interface['name'];
                $isp = $ispMap->get($interfaceName);

                if (! $isp) {
                    Log::warning('Unknown MikroTik interface received via push ingestion.', [
                        'router_name' => $routerName,
                        'interface_name' => $interfaceName,
                    ]);

                    continue;
                }

                $previousSnapshot = IspSnapshot::query()
                    ->where('isp_id', $isp->id)
                    ->latest('recorded_at')
                    ->first();
                $elapsedSeconds = $previousSnapshot?->recorded_at
                    ? max(1, (int) $previousSnapshot->recorded_at->diffInSeconds($recordedAt))
                    : 0;
                $rxBytesTotal = (int) $interface['rx_bytes'];
                $txBytesTotal = (int) $interface['tx_bytes'];

                IspSnapshot::query()->create([
                    'isp_id' => $isp->id,
                    'rx_bps' => $this->counterDeltaCalculator->calculateBps(
                        $rxBytesTotal,
                        $previousSnapshot?->rx_bytes_total,
                        $elapsedSeconds,
                    ),
                    'tx_bps' => $this->counterDeltaCalculator->calculateBps(
                        $txBytesTotal,
                        $previousSnapshot?->tx_bytes_total,
                        $elapsedSeconds,
                    ),
                    'rx_bytes_total' => $rxBytesTotal,
                    'tx_bytes_total' => $txBytesTotal,
                    'recorded_at' => $recordedAt,
                ]);

                $interfacesIngested++;
            }

            $healthIspMap = Isp::query()
                ->where('is_active', true)
                ->whereIn('interface_name', collect($healthItems)->pluck('name')->all())
                ->get()
                ->keyBy('interface_name');

            foreach ($healthItems as $health) {
                $interfaceName = $health['name'];
                $isp = $healthIspMap->get($interfaceName);

                if (! $isp) {
                    Log::warning('Unknown MikroTik health interface received via push ingestion.', [
                        'router_name' => $routerName,
                        'interface_name' => $interfaceName,
                    ]);

                    continue;
                }

                IspHealthSnapshot::query()->create([
                    'isp_id' => $isp->id,
                    'ping_target' => $health['ping_target'] ?? null,
                    'latency_ms' => $health['latency_ms'] ?? null,
                    'packet_loss_percent' => $health['packet_loss_percent'] ?? null,
                    'jitter_ms' => $health['jitter_ms'] ?? null,
                    'status' => $health['status'],
                    'recorded_at' => $recordedAt,
                ]);

                $healthIngested++;
            }
        });

        Log::info('MikroTik push data ingested successfully.', [
            'router_name' => $routerName,
            'queues_ingested' => $queuesIngested,
            'interfaces_ingested' => $interfacesIngested,
            'health_ingested' => $healthIngested,
            'skipped_queues' => $skippedQueues,
            'recorded_at' => $recordedAt->toDateTimeString(),
        ]);

        return [
            'success' => true,
            'message' => 'Push data ingested',
            'queues_ingested' => $queuesIngested,
            'interfaces_ingested' => $interfacesIngested,
            'health_ingested' => $healthIngested,
            'skipped_queues' => array_values(array_unique($skippedQueues)),
        ];
    }
}
