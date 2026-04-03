<?php

namespace App\Services\Mikrotik;

use App\Data\Mikrotik\InterfaceStatData;
use App\Data\Mikrotik\QueueStatData;
use App\Models\Isp;
use App\Models\IspHealthSnapshot;
use App\Models\IspSnapshot;
use App\Models\MonitoredUser;
use App\Models\RouteStatusSnapshot;
use App\Models\UserSnapshot;
use App\Services\Mikrotik\Contracts\MikrotikClientInterface;
use App\Services\Mikrotik\Exceptions\MikrotikException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MikrotikPollingService
{
    public function __construct(
        protected readonly MikrotikClientInterface $client,
        protected readonly MikrotikNormalizer $normalizer,
    ) {
    }

    public function pollInterfaces(?CarbonImmutable $recordedAt = null): Collection
    {
        $recordedAt ??= CarbonImmutable::now();
        $rawInterfaces = collect($this->client->getInterfaceStats(config('mikrotik.polled_interfaces', [])))->keyBy('name');
        $isps = Isp::query()->where('is_active', true)->whereIn('interface_name', config('mikrotik.polled_interfaces', []))->get();

        return $this->mapInterfaces($rawInterfaces, $isps, $recordedAt);
    }

    public function mapInterfaces(Collection $rawInterfaces, Collection $isps, CarbonImmutable $recordedAt): Collection
    {
        return $isps->map(function (Isp $isp) use ($rawInterfaces, $recordedAt): ?InterfaceStatData {
            $raw = $rawInterfaces->get($isp->interface_name);

            if (! $raw) {
                Log::warning('Expected MikroTik interface not found during poll.', [
                    'interface_name' => $isp->interface_name,
                ]);

                return null;
            }

            $previousSnapshot = IspSnapshot::query()->where('isp_id', $isp->id)->latest('recorded_at')->first();
            $elapsedSeconds = $previousSnapshot?->recorded_at
                ? max(1, (int) $previousSnapshot->recorded_at->diffInSeconds($recordedAt))
                : 0;

            return $this->normalizer->normalizeInterface(
                rawInterface: $raw,
                previousRxBytes: $previousSnapshot?->rx_bytes_total,
                previousTxBytes: $previousSnapshot?->tx_bytes_total,
                elapsedSeconds: $elapsedSeconds,
            );
        })->filter();
    }

    public function pollQueues(): Collection
    {
        $rawQueues = collect($this->client->getSimpleQueues())->keyBy('name');
        $users = MonitoredUser::query()
            ->where('is_active', true)
            ->whereIn('queue_name', config('mikrotik.user_queue_names', []))
            ->get();

        return $this->mapQueues($rawQueues, $users);
    }

    public function mapQueues(Collection $rawQueues, Collection $users): Collection
    {
        $knownUserQueues = $users->pluck('queue_name')->all();
        $ignoredQueues = config('mikrotik.excluded_queue_names', []);

        foreach ($rawQueues->keys() as $queueName) {
            if (in_array($queueName, $ignoredQueues, true)) {
                continue;
            }

            if (! in_array($queueName, $knownUserQueues, true)) {
                Log::warning('Unmapped MikroTik queue encountered during poll.', [
                    'queue_name' => $queueName,
                ]);
            }
        }

        return $users->map(function (MonitoredUser $user) use ($rawQueues): ?QueueStatData {
            $raw = $rawQueues->get($user->queue_name);

            if (! $raw) {
                Log::warning('Expected MikroTik queue not found during poll.', [
                    'queue_name' => $user->queue_name,
                ]);

                return null;
            }

            return $this->normalizer->normalizeQueue($raw, $user->throttled_max_limit);
        })->filter();
    }

    public function pollAndPersist(?CarbonImmutable $recordedAt = null): void
    {
        $recordedAt ??= CarbonImmutable::now();

        try {
            $interfaces = $this->pollInterfaces($recordedAt);
            $this->persistInterfaces($interfaces, $recordedAt);
            $this->persistHealthSnapshots($recordedAt);

            $queues = $this->pollQueues();
            $this->persistQueues($queues, $recordedAt);
        } catch (MikrotikException $exception) {
            Log::error('MikroTik polling failed.', [
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function persistInterfaces(Collection $interfaces, CarbonImmutable $recordedAt): void
    {
        $isps = Isp::query()->whereIn('interface_name', $interfaces->pluck('name')->all())->get()->keyBy('interface_name');

        foreach ($interfaces as $interface) {
            $isp = $isps->get($interface->name);

            if (! $isp) {
                Log::warning('Skipping interface snapshot because ISP mapping was not found.', [
                    'interface_name' => $interface->name,
                ]);

                continue;
            }

            IspSnapshot::query()->create([
                'isp_id' => $isp->id,
                'rx_bps' => $interface->rxBps,
                'tx_bps' => $interface->txBps,
                'rx_bytes_total' => $interface->rxBytesTotal,
                'tx_bytes_total' => $interface->txBytesTotal,
                'recorded_at' => $recordedAt,
            ]);

            RouteStatusSnapshot::query()->create([
                'isp_id' => $isp->id,
                'status' => $interface->online === false ? 'offline' : 'online',
                'details' => [
                    'running' => $interface->online,
                    'rx_bps' => $interface->rxBps,
                    'tx_bps' => $interface->txBps,
                ],
                'recorded_at' => $recordedAt,
            ]);
        }
    }

    public function persistQueues(Collection $queues, CarbonImmutable $recordedAt): void
    {
        $users = MonitoredUser::query()->whereIn('queue_name', $queues->pluck('name')->all())->get()->keyBy('queue_name');

        foreach ($queues as $queue) {
            if (in_array($queue->name, config('mikrotik.excluded_queue_names', []), true)) {
                continue;
            }

            $user = $users->get($queue->name);

            if (! $user) {
                Log::warning('Skipping queue snapshot because monitored user mapping was not found.', [
                    'queue_name' => $queue->name,
                ]);

                continue;
            }

            UserSnapshot::query()->create([
                'monitored_user_id' => $user->id,
                'upload_bytes_total' => $queue->uploadBytesTotal,
                'download_bytes_total' => $queue->downloadBytesTotal,
                'total_bytes' => $queue->totalBytes,
                'max_limit' => $queue->maxLimit,
                'state' => $queue->state,
                'recorded_at' => $recordedAt,
            ]);
        }
    }

    public function persistHealthSnapshots(CarbonImmutable $recordedAt): void
    {
        $targets = config('mikrotik.health_targets', []);
        $count = (int) config('mikrotik.health_ping_count', 3);
        $isps = Isp::query()
            ->where('is_active', true)
            ->whereIn('interface_name', array_keys($targets))
            ->get();

        foreach ($isps as $isp) {
            $target = $targets[$isp->interface_name] ?? null;

            if (! $target) {
                continue;
            }

            $health = $this->client->pingStats($target, $count);

            IspHealthSnapshot::query()->create([
                'isp_id' => $isp->id,
                'ping_target' => $target,
                'latency_ms' => $health['latency_ms'] ?? null,
                'packet_loss_percent' => $health['packet_loss_percent'] ?? null,
                'jitter_ms' => $health['jitter_ms'] ?? null,
                'status' => $health['status'] ?? 'unknown',
                'recorded_at' => $recordedAt,
            ]);
        }
    }
}
