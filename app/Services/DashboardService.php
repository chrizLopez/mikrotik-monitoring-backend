<?php

namespace App\Services;

use App\Models\BillingCycle;
use App\Models\Isp;
use App\Models\MonitoredUser;
use App\Models\MonthlyUserSummary;
use App\Services\Support\RangePreset;
use App\Services\Support\RangeService;
use App\Services\Support\SnapshotUsageService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        private readonly BillingCycleService $billingCycleService,
        private readonly LatestSnapshotService $latestSnapshotService,
        private readonly UsageAggregationService $usageAggregationService,
        private readonly RangeService $rangeService,
        private readonly SnapshotUsageService $snapshotUsageService,
    ) {
    }

    public function summary(string $range = 'cycle'): array
    {
        $cycle = $this->resolveCurrentCycleWithFreshSummaries();
        $preset = $this->rangeService->resolve($range, $cycle);
        $summaryCacheKey = sprintf('dashboard:summary:%s:%s', $cycle->id, $preset->key);

        $summary = Cache::remember($summaryCacheKey, now()->addSeconds(30), function () use ($cycle, $preset): array {
            $summaryBase = MonthlyUserSummary::query()
                ->where('billing_cycle_id', $cycle->id)
                ->selectRaw('COUNT(*) as total_users')
                ->selectRaw("SUM(CASE WHEN state = 'THROTTLED' THEN 1 ELSE 0 END) as throttled_users")
                ->selectRaw('COALESCE(SUM(total_bytes), 0) as total_user_traffic')
                ->selectRaw('MAX(last_snapshot_at) as last_user_poll')
                ->first();

            $latestIspPoll = $this->latestSnapshotService->latestIspSnapshots()->max('recorded_at');
            $activeIspCount = Isp::query()
                ->where('is_active', true)
                ->whereExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('route_status_snapshots as rss')
                        ->whereColumn('rss.isp_id', 'isps.id')
                        ->whereRaw('rss.recorded_at = (select max(recorded_at) from route_status_snapshots where isp_id = isps.id)')
                        ->where('rss.status', 'online');
                })
                ->count();

            $totalUserTrafficThisCycle = (int) ($summaryBase?->total_user_traffic ?? 0);
            $totalIspTrafficThisCycle = Cache::remember(
                sprintf('dashboard:isp-cycle-total:%s', $cycle->id),
                now()->addSeconds(30),
                fn (): int => $this->usageAggregationService->totalIspTrafficForCycle($cycle),
            );

            $totalUserTrafficForRange = $preset->key === 'cycle'
                ? $totalUserTrafficThisCycle
                : $this->sumUserTrafficForPreset($preset);

            $totalIspTrafficForRange = $preset->key === 'cycle'
                ? $totalIspTrafficThisCycle
                : $this->sumIspTrafficForPreset($preset);

            $lastPoll = collect([$summaryBase?->last_user_poll, $latestIspPoll])->filter()->sort()->last();

            return [
                'range' => $preset->key,
                'total_monitored_users' => (int) ($summaryBase?->total_users ?? 0),
                'throttled_user_count' => (int) ($summaryBase?->throttled_users ?? 0),
                'active_isp_count' => $activeIspCount,
                'total_isp_traffic_this_cycle' => $totalIspTrafficThisCycle,
                'total_user_traffic_this_cycle' => $totalUserTrafficThisCycle,
                'total_isp_traffic_for_range' => (int) $totalIspTrafficForRange,
                'total_user_traffic_for_range' => (int) $totalUserTrafficForRange,
                'last_poll_timestamp' => $lastPoll,
            ];
        });

        return [
            ...$summary,
            'billing_cycle' => $cycle,
        ];
    }

    public function currentIspStat(Isp $isp): Isp
    {
        return $this->currentIspStats()->firstWhere('id', $isp->id) ?? $isp;
    }

    public function currentIspStats(): Collection
    {
        return Isp::query()
            ->where('is_active', true)
            ->with([
                'snapshots' => fn ($query) => $query->latest('recorded_at')->limit(1),
                'routeStatusSnapshots' => fn ($query) => $query->latest('recorded_at')->limit(1),
            ])
            ->orderByRaw('display_order is null')
            ->orderBy('display_order')
            ->get();
    }

    public function currentUserStat(MonitoredUser $user): MonitoredUser
    {
        $cycle = $this->resolveCurrentCycleWithFreshSummaries();

        return MonitoredUser::query()
            ->whereKey($user->id)
            ->leftJoin('monthly_user_summaries as mus', function ($join) use ($cycle): void {
                $join->on('mus.monitored_user_id', '=', 'monitored_users.id')
                    ->where('mus.billing_cycle_id', '=', $cycle->id);
            })
            ->select('monitored_users.*')
            ->selectRaw('COALESCE(mus.total_bytes, 0) as total_bytes')
            ->selectRaw('COALESCE(mus.upload_bytes, 0) as upload_bytes')
            ->selectRaw('COALESCE(mus.download_bytes, 0) as download_bytes')
            ->selectRaw('COALESCE(mus.quota_bytes, monitored_users.monthly_quota_bytes) as quota_bytes')
            ->selectRaw('COALESCE(mus.remaining_bytes, monitored_users.monthly_quota_bytes) as remaining_bytes')
            ->selectRaw('COALESCE(mus.usage_percent, 0) as usage_percent')
            ->selectRaw("COALESCE(mus.state, 'NORMAL') as state")
            ->selectRaw('mus.current_max_limit as current_max_limit')
            ->selectRaw('mus.last_snapshot_at as last_snapshot_at')
            ->firstOrFail();
    }

    public function paginatedUserStats(
        ?string $search = null,
        ?string $group = null,
        ?string $state = null,
        string $sort = 'name',
        string $direction = 'desc',
        int $perPage = 15,
    ): LengthAwarePaginator {
        $cycle = $this->resolveCurrentCycleWithFreshSummaries();

        $query = MonitoredUser::query()
            ->where('monitored_users.is_active', true)
            ->where('monitored_users.queue_name', '!=', config('dashboard.group_totals_queue'))
            ->leftJoin('monthly_user_summaries as mus', function ($join) use ($cycle): void {
                $join->on('mus.monitored_user_id', '=', 'monitored_users.id')
                    ->where('mus.billing_cycle_id', '=', $cycle->id);
            })
            ->select('monitored_users.*')
            ->selectRaw('COALESCE(mus.total_bytes, 0) as total_bytes')
            ->selectRaw('COALESCE(mus.upload_bytes, 0) as upload_bytes')
            ->selectRaw('COALESCE(mus.download_bytes, 0) as download_bytes')
            ->selectRaw('COALESCE(mus.quota_bytes, monitored_users.monthly_quota_bytes) as quota_bytes')
            ->selectRaw('COALESCE(mus.remaining_bytes, monitored_users.monthly_quota_bytes) as remaining_bytes')
            ->selectRaw('COALESCE(mus.usage_percent, 0) as usage_percent')
            ->selectRaw("COALESCE(mus.state, 'NORMAL') as state")
            ->selectRaw('mus.current_max_limit as current_max_limit')
            ->selectRaw('mus.last_snapshot_at as last_snapshot_at');

        if ($search !== null) {
            $query->where('monitored_users.name', 'like', '%'.$search.'%');
        }

        if ($group !== null) {
            $query->where('monitored_users.group_name', $group);
        }

        if ($state !== null) {
            $query->whereRaw("COALESCE(mus.state, 'NORMAL') = ?", [$state]);
        }

        match ($sort) {
            'used_bytes' => $query->orderBy('total_bytes', $direction)->orderBy('monitored_users.name'),
            'remaining_quota' => $query->orderBy('remaining_bytes', $direction)->orderBy('monitored_users.name'),
            'usage_percent' => $query->orderBy('usage_percent', $direction)->orderBy('monitored_users.name'),
            'last_updated' => $query->orderBy('last_snapshot_at', $direction)->orderBy('monitored_users.name'),
            default => $query->orderBy('monitored_users.name', $direction === 'desc' ? 'desc' : 'asc'),
        };

        return $query->paginate($perPage)->withQueryString();
    }

    public function topUsers(string $range, int $limit): Collection
    {
        $cycle = $this->resolveCurrentCycleWithFreshSummaries();
        $preset = $this->rangeService->resolve($range, $cycle);

        if ($preset->key === 'cycle') {
            return MonthlyUserSummary::query()
                ->with('monitoredUser')
                ->whereBelongsTo($cycle)
                ->orderByDesc('total_bytes')
                ->limit($limit)
                ->get();
        }

        return MonitoredUser::query()
            ->where('is_active', true)
            ->where('queue_name', '!=', config('dashboard.group_totals_queue'))
            ->get()
            ->map(function (MonitoredUser $user) use ($preset): MonitoredUser {
                $usage = $this->snapshotUsageService->computeRangeUsage(
                    $user->snapshots(),
                    $preset->start,
                    $preset->end,
                    ['upload_bytes_total', 'download_bytes_total'],
                );
                $user->setAttribute('range_total_bytes', $usage['total_bytes']);

                return $user;
            })
            ->sortByDesc('range_total_bytes')
            ->take($limit)
            ->values();
    }

    public function groupUsage(string $range): array
    {
        $cycle = $this->resolveCurrentCycleWithFreshSummaries();
        $preset = $this->rangeService->resolve($range, $cycle);

        if ($preset->key === 'cycle') {
            $summaries = MonthlyUserSummary::query()
                ->selectRaw('monitored_users.group_name, SUM(monthly_user_summaries.total_bytes) as total_bytes, COUNT(monthly_user_summaries.id) as user_count')
                ->join('monitored_users', 'monitored_users.id', '=', 'monthly_user_summaries.monitored_user_id')
                ->where('billing_cycle_id', $cycle->id)
                ->groupBy('monitored_users.group_name')
                ->get()
                ->keyBy('group_name');

            return [
                [
                    'group_name' => 'Group A',
                    'total_bytes' => (int) ($summaries['Group A']->total_bytes ?? 0),
                    'user_count' => (int) ($summaries['Group A']->user_count ?? 0),
                ],
                [
                    'group_name' => 'Group B',
                    'total_bytes' => (int) ($summaries['Group B']->total_bytes ?? 0),
                    'user_count' => (int) ($summaries['Group B']->user_count ?? 0),
                ],
            ];
        }

        $totals = ['Group A' => 0, 'Group B' => 0];
        $counts = ['Group A' => 0, 'Group B' => 0];

        MonitoredUser::query()
            ->where('is_active', true)
            ->where('queue_name', '!=', config('dashboard.group_totals_queue'))
            ->get()
            ->each(function (MonitoredUser $user) use (&$counts, &$totals, $preset): void {
                $group = $user->group_name;

                if (! array_key_exists($group, $totals)) {
                    return;
                }

                $usage = $this->snapshotUsageService->computeRangeUsage(
                    $user->snapshots(),
                    $preset->start,
                    $preset->end,
                    ['upload_bytes_total', 'download_bytes_total'],
                );

                $totals[$group] += $usage['total_bytes'];
                $counts[$group]++;
            });

        return [
            ['group_name' => 'Group A', 'total_bytes' => $totals['Group A'], 'user_count' => $counts['Group A']],
            ['group_name' => 'Group B', 'total_bytes' => $totals['Group B'], 'user_count' => $counts['Group B']],
        ];
    }

    public function ispHistory(Isp $isp, string $range): array
    {
        $cycle = $this->billingCycleService->resolveCurrent();
        $preset = $this->rangeService->resolve($range, $cycle);
        $points = [];
        $previous = $isp->snapshots()
            ->where('recorded_at', '<', $preset->start)
            ->orderByDesc('recorded_at')
            ->first();

        $isp->snapshots()
            ->whereBetween('recorded_at', [$preset->start, $preset->end])
            ->orderBy('recorded_at')
            ->get()
            ->each(function ($snapshot) use (&$points, &$previous, $preset): void {
                $bucket = $this->rangeService->bucketStart(CarbonImmutable::instance($snapshot->recorded_at), $preset->bucket)->toIso8601String();
                $delta = $this->snapshotUsageService->sumPositiveDeltasFromSnapshots(
                    collect([$snapshot]),
                    ['rx_bytes_total', 'tx_bytes_total'],
                    $previous,
                    false,
                );

                if (! isset($points[$bucket])) {
                    $points[$bucket] = [
                        'timestamp' => $bucket,
                        'rx_bps' => 0,
                        'tx_bps' => 0,
                        'samples' => 0,
                        'download_bytes' => 0,
                        'upload_bytes' => 0,
                        'total_bytes' => 0,
                        'rx_bytes_total' => 0,
                        'tx_bytes_total' => 0,
                    ];
                }

                $points[$bucket]['rx_bps'] += (int) $snapshot->rx_bps;
                $points[$bucket]['tx_bps'] += (int) $snapshot->tx_bps;
                $points[$bucket]['samples']++;
                $points[$bucket]['download_bytes'] += $delta['rx_bytes_total'] ?? 0;
                $points[$bucket]['upload_bytes'] += $delta['tx_bytes_total'] ?? 0;
                $points[$bucket]['total_bytes'] += ($delta['rx_bytes_total'] ?? 0) + ($delta['tx_bytes_total'] ?? 0);
                $points[$bucket]['rx_bytes_total'] = (int) $snapshot->rx_bytes_total;
                $points[$bucket]['tx_bytes_total'] = (int) $snapshot->tx_bytes_total;
                $previous = $snapshot;
            });

        $totals = collect($points)->reduce(function (array $carry, array $point): array {
            $carry['download_bytes'] += $point['download_bytes'];
            $carry['upload_bytes'] += $point['upload_bytes'];
            $carry['total_bytes'] += $point['total_bytes'];

            return $carry;
        }, ['download_bytes' => 0, 'upload_bytes' => 0, 'total_bytes' => 0]);

        return [
            'range' => $preset,
            'totals' => $totals,
            'points' => collect($points)->values()->map(function (array $point): array {
                $samples = max(1, $point['samples']);

                return [
                    'timestamp' => $point['timestamp'],
                    'rx_bps' => (int) round($point['rx_bps'] / $samples),
                    'tx_bps' => (int) round($point['tx_bps'] / $samples),
                    'total_bps' => (int) round(($point['rx_bps'] + $point['tx_bps']) / $samples),
                    'download_bytes' => $point['download_bytes'],
                    'upload_bytes' => $point['upload_bytes'],
                    'total_bytes' => $point['total_bytes'],
                    'rx_bytes_total' => $point['rx_bytes_total'],
                    'tx_bytes_total' => $point['tx_bytes_total'],
                    'current_total_bytes' => $point['rx_bytes_total'] + $point['tx_bytes_total'],
                ];
            })->all(),
        ];
    }

    public function userHistory(MonitoredUser $user, string $range): array
    {
        $cycle = $this->resolveCurrentCycleWithFreshSummaries();
        $preset = $this->rangeService->resolve($range, $cycle);
        $points = [];
        $previous = $user->snapshots()
            ->where('recorded_at', '<', $preset->start)
            ->orderByDesc('recorded_at')
            ->first();

        $user->snapshots()
            ->whereBetween('recorded_at', [$preset->start, $preset->end])
            ->orderBy('recorded_at')
            ->get()
            ->each(function ($snapshot) use (&$points, &$previous, $preset): void {
                $bucket = $this->rangeService->bucketStart(CarbonImmutable::instance($snapshot->recorded_at), $preset->bucket)->toIso8601String();

                if (! isset($points[$bucket])) {
                    $points[$bucket] = [
                        'timestamp' => $bucket,
                        'upload_bytes' => 0,
                        'download_bytes' => 0,
                        'total_bytes' => 0,
                        'state' => $snapshot->state,
                        'current_max_limit' => $snapshot->max_limit,
                    ];
                }

                $delta = $this->snapshotUsageService->sumPositiveDeltasFromSnapshots(
                    collect([$snapshot]),
                    ['upload_bytes_total', 'download_bytes_total'],
                    $previous,
                );
                $upload = $delta['upload_bytes_total'] ?? 0;
                $download = $delta['download_bytes_total'] ?? 0;

                $points[$bucket]['upload_bytes'] += $upload;
                $points[$bucket]['download_bytes'] += $download;
                $points[$bucket]['total_bytes'] += $upload + $download;
                $points[$bucket]['state'] = $snapshot->state;
                $points[$bucket]['current_max_limit'] = $snapshot->max_limit;
                $previous = $snapshot;
            });

        return [
            'range' => $preset,
            'totals' => [
                'upload_bytes' => array_sum(array_column($points, 'upload_bytes')),
                'download_bytes' => array_sum(array_column($points, 'download_bytes')),
                'total_bytes' => array_sum(array_column($points, 'total_bytes')),
            ],
            'points' => array_values($points),
        ];
    }

    public function resolveCurrentCycleWithFreshSummaries(): BillingCycle
    {
        $cycle = $this->billingCycleService->resolveCurrent();
        $summaryCount = MonthlyUserSummary::query()
            ->where('billing_cycle_id', $cycle->id)
            ->count();

        if ($summaryCount === 0) {
            $this->usageAggregationService->aggregateCycle($cycle);
        }

        return $cycle;
    }

    private function sumUserTrafficForPreset(RangePreset $preset): int
    {
        return MonitoredUser::query()
            ->where('is_active', true)
            ->where('queue_name', '!=', config('dashboard.group_totals_queue'))
            ->get()
            ->sum(function (MonitoredUser $user) use ($preset): int {
                return (int) $this->snapshotUsageService->computeRangeUsage(
                    $user->snapshots(),
                    $preset->start,
                    $preset->end,
                    ['upload_bytes_total', 'download_bytes_total'],
                )['total_bytes'];
            });
    }

    private function sumIspTrafficForPreset(RangePreset $preset): int
    {
        return Isp::query()
            ->where('is_active', true)
            ->get()
            ->sum(function (Isp $isp) use ($preset): int {
                return (int) $this->snapshotUsageService->computeRangeUsage(
                    $isp->snapshots(),
                    $preset->start,
                    $preset->end,
                    ['rx_bytes_total', 'tx_bytes_total'],
                    false,
                )['total_bytes'];
            });
    }
}
