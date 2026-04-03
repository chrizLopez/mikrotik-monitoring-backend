<?php

namespace App\Services\TrafficAnalytics;

use App\Models\Isp;
use App\Models\MonitoredUser;
use App\Models\TrafficEntityDailySummary;
use App\Models\TrafficObservation;
use App\Services\TrafficAnalytics\Contracts\AnalyticsSourceInterface;
use Carbon\CarbonImmutable;

class AnalyticsIngestionService
{
    /**
     * @param  iterable<array<string, mixed>>  $observations
     * @return array{imported: int, unknown: int}
     */
    public function ingest(iterable $observations, AnalyticsSourceInterface $source, EntityResolverService $resolver): array
    {
        $imported = 0;
        $unknown = 0;

        foreach ($observations as $observation) {
            $resolved = $resolver->resolve($observation);
            $recordedAt = CarbonImmutable::parse((string) ($observation['recorded_at'] ?? now()->toIso8601String()));
            $totalBytes = (int) ($observation['total_bytes'] ?? ((int) ($observation['upload_bytes'] ?? 0) + (int) ($observation['download_bytes'] ?? 0)));

            $trafficObservation = TrafficObservation::query()->create([
                'source_type' => $source->sourceType(),
                'monitored_user_id' => $this->resolveUserId($observation['monitored_user'] ?? null),
                'isp_id' => $this->resolveIspId($observation['isp'] ?? null),
                'traffic_entity_id' => $resolved['entity']->id,
                'observed_name' => $observation['observed_name'] ?? null,
                'destination_host' => $observation['destination_host'] ?? null,
                'destination_ip' => $observation['destination_ip'] ?? null,
                'category_name' => $observation['category_name'] ?? $resolved['entity']->category_name,
                'app_name' => $observation['app_name'] ?? null,
                'upload_bytes' => (int) ($observation['upload_bytes'] ?? 0),
                'download_bytes' => (int) ($observation['download_bytes'] ?? 0),
                'total_bytes' => $totalBytes,
                'protocol' => $observation['protocol'] ?? null,
                'confidence_score' => round((float) ($observation['confidence_score'] ?? $resolved['confidence']), 2),
                'recorded_at' => $recordedAt,
            ]);

            $summary = TrafficEntityDailySummary::query()->firstOrNew([
                'traffic_entity_id' => $trafficObservation->traffic_entity_id,
                'monitored_user_id' => $trafficObservation->monitored_user_id,
                'isp_id' => $trafficObservation->isp_id,
                'date' => $recordedAt->toDateString(),
            ]);
            $summary->upload_bytes = (int) $summary->upload_bytes + (int) $trafficObservation->upload_bytes;
            $summary->download_bytes = (int) $summary->download_bytes + (int) $trafficObservation->download_bytes;
            $summary->total_bytes = (int) $summary->total_bytes + $totalBytes;
            $summary->save();

            $imported++;
            if ($resolved['entity']->entity_type === 'unknown') {
                $unknown++;
            }
        }

        return ['imported' => $imported, 'unknown' => $unknown];
    }

    public function source(string $source): AnalyticsSourceInterface
    {
        return match ($source) {
            'ntopng' => app(\App\Services\TrafficAnalytics\Sources\NtopngAnalyticsSource::class),
            'zeek' => app(\App\Services\TrafficAnalytics\Sources\ZeekAnalyticsSource::class),
            'flow', 'manual_import' => app(\App\Services\TrafficAnalytics\Sources\FlowAnalyticsSource::class),
            default => throw new \InvalidArgumentException("Unsupported analytics source [{$source}]."),
        };
    }

    private function resolveUserId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $string = (string) $value;

        return MonitoredUser::query()
            ->whereKey($string)
            ->orWhere('name', $string)
            ->orWhere('queue_name', $string)
            ->value('id');
    }

    private function resolveIspId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $string = (string) $value;

        return Isp::query()
            ->whereKey($string)
            ->orWhere('name', $string)
            ->orWhere('interface_name', $string)
            ->value('id');
    }
}
