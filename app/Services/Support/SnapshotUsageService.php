<?php

namespace App\Services\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

class SnapshotUsageService
{
    public function __construct(
        private readonly DeltaService $deltaService,
        private readonly QuotaCalculator $quotaCalculator,
    ) {
    }

    /**
     * @param  Collection<int, object>  $snapshots
     * @param  array<int, string>  $fields
     * @return array<string, int>
     */
    public function sumPositiveDeltasFromSnapshots(
        Collection $snapshots,
        array $fields,
        object|null $previousSnapshot = null,
        bool $includeInitialWithoutPredecessor = true,
    ): array
    {
        $totals = [];

        foreach ($fields as $field) {
            $totals[$field] = 0;
        }

        $previous = $previousSnapshot;

        foreach ($snapshots as $snapshot) {
            foreach ($fields as $field) {
                $currentValue = $this->normalizeNullableInt($snapshot->{$field} ?? null);
                $previousValue = $this->normalizeNullableInt($previous?->{$field} ?? null);

                if ($previous === null && ! $includeInitialWithoutPredecessor) {
                    $delta = 0;
                } elseif ($previous !== null && $previousValue === null) {
                    $delta = 0;
                } else {
                    $delta = $this->deltaService->safeDelta($currentValue, $previousValue);
                }

                $totals[$field] += $delta;
            }

            $previous = $snapshot;
        }

        return $totals;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>|Relation<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<int, string>  $fields
     * @return array{
     *     started_at: string,
     *     ended_at: string,
     *     sample_count: int,
     *     last_snapshot_at: ?string,
     *     last_snapshot: object|null,
     *     totals: array<string, int>,
     *     total_bytes: int
     * }
     */
    public function computeRangeUsage(
        Builder|Relation $query,
        CarbonImmutable $start,
        CarbonImmutable $end,
        array $fields,
        bool $includeInitialWithoutPredecessor = true,
    ): array
    {
        $previousSnapshot = (clone $query)
            ->where('recorded_at', '<', $start)
            ->orderByDesc('recorded_at')
            ->first();

        $snapshots = (clone $query)
            ->whereBetween('recorded_at', [$start, $end])
            ->orderBy('recorded_at')
            ->get();

        $totals = $this->sumPositiveDeltasFromSnapshots($snapshots, $fields, $previousSnapshot, $includeInitialWithoutPredecessor);
        $totalBytes = array_sum($totals);
        $lastSnapshot = $snapshots->last();

        return [
            'started_at' => $start->toIso8601String(),
            'ended_at' => $end->toIso8601String(),
            'sample_count' => $snapshots->count(),
            'last_snapshot_at' => $lastSnapshot?->recorded_at?->toIso8601String(),
            'last_snapshot' => $lastSnapshot,
            'totals' => $totals,
            'total_bytes' => max(0, $totalBytes),
        ];
    }

    /**
     * @return array{quota_bytes:int, total_bytes:int, remaining_bytes:int, usage_percent:float, state:string|null, current_max_limit:string|null}
     */
    public function computeQuotaState(
        int $totalBytes,
        int $quotaBytes,
        ?string $state = null,
        ?string $currentMaxLimit = null,
    ): array {
        return [
            'quota_bytes' => max(0, $quotaBytes),
            'total_bytes' => max(0, $totalBytes),
            'remaining_bytes' => $this->computeRemainingBytes($totalBytes, $quotaBytes),
            'usage_percent' => $this->quotaCalculator->usagePercent($totalBytes, $quotaBytes),
            'state' => $state,
            'current_max_limit' => $currentMaxLimit,
        ];
    }

    public function computeRemainingBytes(int $usedBytes, int $quotaBytes): int
    {
        return $this->quotaCalculator->remainingBytes(max(0, $usedBytes), max(0, $quotaBytes));
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return (int) $value;
    }
}
