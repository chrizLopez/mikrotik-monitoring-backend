<?php

namespace App\Services\TrafficAnalytics;

use App\Models\Isp;
use App\Models\MonitoredUser;
use App\Models\TrafficEntity;
use App\Models\TrafficObservation;
use App\Services\BillingCycleService;
use App\Services\Support\RangePreset;
use App\Services\Support\RangeService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TrafficAnalyticsService
{
    public function __construct(
        private readonly BillingCycleService $billingCycleService,
        private readonly RangeService $rangeService,
    ) {
    }

    public function topSites(string $range, int $limit = 10): array
    {
        return $this->topEntities($range, ['website'], $limit);
    }

    public function topApps(string $range, int $limit = 10): array
    {
        return $this->topEntities($range, ['app'], $limit);
    }

    public function topGames(string $range, int $limit = 10): array
    {
        return $this->topEntities($range, ['game_service'], $limit);
    }

    public function topCategories(string $range, int $limit = 10): array
    {
        $preset = $this->resolveRange($range);
        $items = $this->aggregateObservationQuery($preset)
            ->selectRaw('COALESCE(traffic_entities.category_name, traffic_observations.category_name, ?) as label', ['Unknown Encrypted'])
            ->selectRaw('SUM(traffic_observations.upload_bytes) as upload_bytes')
            ->selectRaw('SUM(traffic_observations.download_bytes) as download_bytes')
            ->selectRaw('SUM(traffic_observations.total_bytes) as total_bytes')
            ->groupBy('label')
            ->orderByDesc('total_bytes')
            ->limit($limit)
            ->get();

        return [
            'range' => $this->serializeRange($preset),
            'items' => $items->map(fn ($item): array => [
                'label' => $item->label,
                'upload_bytes' => (int) $item->upload_bytes,
                'download_bytes' => (int) $item->download_bytes,
                'total_bytes' => (int) $item->total_bytes,
            ])->all(),
        ];
    }

    public function userTopDestinations(MonitoredUser $user, string $range, int $limit = 10): array
    {
        return $this->topEntities($range, null, $limit, fn (Builder $query) => $query->where('traffic_observations.monitored_user_id', $user->id));
    }

    public function ispTopDestinations(Isp $isp, string $range, int $limit = 10): array
    {
        return $this->topEntities($range, null, $limit, fn (Builder $query) => $query->where('traffic_observations.isp_id', $isp->id));
    }

    public function groupTopDestinations(string $group, string $range, int $limit = 10): array
    {
        $names = config('traffic_analytics.groups.'.strtoupper($group), []);

        return $this->topEntities($range, null, $limit, function (Builder $query) use ($names): void {
            $ids = MonitoredUser::query()->whereIn('name', $names)->pluck('id');
            $query->whereIn('traffic_observations.monitored_user_id', $ids);
        });
    }

    public function history(int $entityId, string $range): array
    {
        $preset = $this->resolveRange($range);
        $entity = TrafficEntity::query()->findOrFail($entityId);
        $points = $this->observationModelQuery($preset)
            ->where('traffic_entity_id', $entityId)
            ->get()
            ->groupBy(fn ($item) => $this->bucketFor($preset, CarbonImmutable::parse($item->recorded_at)))
            ->map(fn (Collection $items, string $bucket): array => [
                'timestamp' => $bucket,
                'upload_bytes' => (int) $items->sum('upload_bytes'),
                'download_bytes' => (int) $items->sum('download_bytes'),
                'total_bytes' => (int) $items->sum('total_bytes'),
            ])
            ->values()
            ->all();

        return [
            'range' => $this->serializeRange($preset),
            'entity' => $this->serializeEntity($entity),
            'points' => $points,
        ];
    }

    public function overview(string $range): array
    {
        $preset = $this->resolveRange($range);
        $rows = $this->observationModelQuery($preset)->with('entity')->get();
        $total = (int) $rows->sum('total_bytes');
        $unknown = (int) $rows->filter(fn ($row) => $row->entity?->entity_type === 'unknown')->sum('total_bytes');
        $classified = max(0, $total - $unknown);

        return [
            'range' => $this->serializeRange($preset),
            'total_classified_bytes' => $classified,
            'total_unclassified_bytes' => $unknown,
            'classification_coverage_percent' => $total > 0 ? round(($classified / $total) * 100, 2) : 0,
            'top_categories' => $this->topCategories($range, 5)['items'],
            'top_sites' => $this->topSites($range, 5)['items'],
            'top_apps' => $this->topApps($range, 5)['items'],
            'unknown_encrypted_bytes' => $unknown,
        ];
    }

    public function exportRows(string $range): array
    {
        $preset = $this->resolveRange($range);

        return $this->observationModelQuery($preset)
            ->orderByDesc('total_bytes')
            ->with(['entity', 'monitoredUser', 'isp'])
            ->get()
            ->map(fn (TrafficObservation $observation): array => [
                'recorded_at' => $observation->recorded_at?->toIso8601String(),
                'source_type' => $observation->source_type,
                'user' => $observation->monitoredUser?->name,
                'group_name' => $observation->monitoredUser?->group_name,
                'isp' => $observation->isp?->name,
                'entity' => $observation->entity?->display_name ?? $observation->observed_name,
                'entity_type' => $observation->entity?->entity_type,
                'category_name' => $observation->entity?->category_name ?? $observation->category_name,
                'destination_host' => $observation->destination_host,
                'app_name' => $observation->app_name,
                'upload_bytes' => (int) $observation->upload_bytes,
                'download_bytes' => (int) $observation->download_bytes,
                'total_bytes' => (int) $observation->total_bytes,
                'confidence_score' => $observation->confidence_score,
            ])->all();
    }

    private function topEntities(string $range, ?array $entityTypes, int $limit, ?callable $scope = null): array
    {
        $preset = $this->resolveRange($range);
        $query = $this->aggregateObservationQuery($preset);

        if ($entityTypes !== null) {
            $query->whereIn('traffic_entities.entity_type', $entityTypes);
        }

        if ($scope) {
            $scope($query);
        }

        $items = $query
            ->selectRaw('traffic_entities.id as entity_id')
            ->selectRaw('traffic_entities.entity_type as entity_type')
            ->selectRaw('traffic_entities.display_name as display_name')
            ->selectRaw('traffic_entities.category_name as category_name')
            ->selectRaw('AVG(traffic_observations.confidence_score) as confidence_score')
            ->selectRaw('SUM(traffic_observations.upload_bytes) as upload_bytes')
            ->selectRaw('SUM(traffic_observations.download_bytes) as download_bytes')
            ->selectRaw('SUM(traffic_observations.total_bytes) as total_bytes')
            ->groupBy('traffic_entities.id', 'traffic_entities.entity_type', 'traffic_entities.display_name', 'traffic_entities.category_name')
            ->orderByDesc('total_bytes')
            ->limit($limit)
            ->get();

        return [
            'range' => $this->serializeRange($preset),
            'items' => $items->map(fn ($item): array => [
                'entity_id' => (int) $item->entity_id,
                'entity_type' => $item->entity_type,
                'display_name' => $item->display_name,
                'category_name' => $item->category_name,
                'confidence_score' => round((float) $item->confidence_score, 2),
                'confidence_label' => $this->confidenceLabel((float) $item->confidence_score),
                'upload_bytes' => (int) $item->upload_bytes,
                'download_bytes' => (int) $item->download_bytes,
                'total_bytes' => (int) $item->total_bytes,
            ])->all(),
        ];
    }

    private function observationModelQuery(RangePreset $preset): Builder
    {
        $excludedIds = MonitoredUser::query()
            ->whereIn('queue_name', config('traffic_analytics.excluded_queues', []))
            ->pluck('id');

        return TrafficObservation::query()
            ->whereBetween('recorded_at', [$preset->start, $preset->end])
            ->when($excludedIds->isNotEmpty(), fn (Builder $query) => $query->whereNotIn('monitored_user_id', $excludedIds));
    }

    private function aggregateObservationQuery(RangePreset $preset): Builder
    {
        return $this->observationModelQuery($preset)
            ->leftJoin('traffic_entities', 'traffic_entities.id', '=', 'traffic_observations.traffic_entity_id');
    }

    private function resolveRange(string $range): RangePreset
    {
        return $this->rangeService->resolve($range, $this->billingCycleService->resolveCurrent());
    }

    private function serializeRange(RangePreset $preset): array
    {
        return [
            'key' => $preset->key,
            'label' => $preset->label,
            'start' => $preset->start->toIso8601String(),
            'end' => $preset->end->toIso8601String(),
            'bucket' => $preset->bucket,
        ];
    }

    private function serializeEntity(TrafficEntity $entity): array
    {
        return [
            'id' => $entity->id,
            'entity_type' => $entity->entity_type,
            'canonical_name' => $entity->canonical_name,
            'display_name' => $entity->display_name,
            'category_name' => $entity->category_name,
            'vendor_name' => $entity->vendor_name,
            'metadata' => $entity->metadata,
        ];
    }

    private function bucketFor(RangePreset $preset, CarbonImmutable $at): string
    {
        return match ($preset->bucket) {
            'hour' => $at->startOfHour()->toIso8601String(),
            'day' => $at->startOfDay()->toIso8601String(),
            default => $at->startOfHour()->toIso8601String(),
        };
    }

    private function confidenceLabel(float $score): string
    {
        return match (true) {
            $score >= 0.9 => 'high',
            $score >= 0.6 => 'medium',
            $score > 0 => 'low',
            default => 'unknown',
        };
    }
}
