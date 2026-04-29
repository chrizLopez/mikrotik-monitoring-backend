<?php

namespace App\Services;

use App\Models\BillingCycle;
use App\Models\Isp;
use App\Models\IspSnapshot;
use App\Models\MonitoredUser;
use App\Models\MonthlyUserSummary;
use App\Services\Support\RangePreset;
use App\Services\Support\RangeService;
use App\Services\Support\SnapshotUsageService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class DashboardService
{
    public function __construct(
        private readonly BillingCycleService $billingCycleService,
        private readonly UsageAggregationService $usageAggregationService,
        private readonly RangeService $rangeService,
        private readonly SnapshotUsageService $snapshotUsageService,
    ) {
    }

    public function summary(string $range = 'cycle'): array
    {
        $cycle = $this->resolveCurrentCycleWithFreshSummaries();
        $preset = $this->rangeService->resolve($range, $cycle);
        $summaries = MonthlyUserSummary::query()->whereBelongsTo($cycle)->get();
        $isps = $this->currentIspStats();
        $latestUserPoll = $summaries->max('last_snapshot_at');
        $latestIspPoll = $isps->max(fn (Isp $isp) => $isp->snapshots->first()?->recorded_at);
        $lastPoll = collect([$latestUserPoll, $latestIspPoll])->filter()->sort()->last();
        $totalUserTrafficThisCycle = (int) $summaries->sum('total_bytes');
        $totalIspTrafficThisCycle = $this->usageAggregationService->totalIspTrafficForCycle($cycle);
        $groupDefinitions = $this->groupDefinitions();
        $starlinkUsage = $this->starlinkUsage($cycle);
        $smartbroTotal = $this->smartbroTotalUsage($cycle);

        $totalUserTrafficForRange = $preset->key === 'cycle'
            ? $totalUserTrafficThisCycle
            : MonitoredUser::query()
                ->where('is_active', true)
                ->where('queue_name', '!=', config('dashboard.group_totals_queue'))
                ->get()
                ->sum(function (MonitoredUser $user) use ($preset): int {
                    $usage = $this->snapshotUsageService->computeRangeUsage(
                        $user->snapshots(),
                        $preset->start,
                        $preset->end,
                        ['upload_bytes_total', 'download_bytes_total'],
                    );

                    return (int) $usage['total_bytes'];
                });

        $totalIspTrafficForRange = $preset->key === 'cycle'
            ? $totalIspTrafficThisCycle
            : Isp::query()
                ->where('is_active', true)
                ->get()
                ->sum(function (Isp $isp) use ($preset): int {
                    $usage = $this->snapshotUsageService->computeRangeUsage(
                        $isp->snapshots(),
                        $preset->start,
                        $preset->end,
                        ['rx_bytes_total', 'tx_bytes_total'],
                        false,
                    );

                    return (int) $usage['total_bytes'];
                });

        return [
            'range' => $preset->key,
            'billing_cycle' => $cycle,
            'total_monitored_users' => MonitoredUser::query()
                ->where('is_active', true)
                ->where('queue_name', '!=', config('dashboard.group_totals_queue'))
                ->count(),
            'throttled_user_count' => $summaries->where('state', 'THROTTLED')->count(),
            'active_isp_count' => $isps->where('status', 'online')->count(),
            'total_isp_traffic_this_cycle' => $totalIspTrafficThisCycle,
            'total_user_traffic_this_cycle' => $totalUserTrafficThisCycle,
            'total_isp_traffic_for_range' => (int) $totalIspTrafficForRange,
            'total_user_traffic_for_range' => (int) $totalUserTrafficForRange,
            'last_poll_timestamp' => $lastPoll,
            'group_policies' => array_values($groupDefinitions),
            'starlink_usage' => $starlinkUsage,
            'smartbro_total' => $smartbroTotal,
            'distribution_note' => config('dashboard.distribution_note'),
        ];
    }

    public function currentIspStats(): Collection
    {
        $cycle = $this->billingCycleService->resolveCurrent();
        $preset = $this->rangeService->resolve('cycle', $cycle);

        return Isp::query()
            ->where('is_active', true)
            ->with([
                'snapshots' => fn ($query) => $query->latest('recorded_at')->limit(1),
                'routeStatusSnapshots' => fn ($query) => $query->latest('recorded_at')->limit(1),
            ])
            ->orderByRaw('display_order is null')
            ->orderBy('display_order')
            ->get()
            ->map(function (Isp $isp) use ($preset): Isp {
                $usage = $this->snapshotUsageService->computeRangeUsage(
                    $isp->snapshots(),
                    $preset->start,
                    $preset->end,
                    ['rx_bytes_total', 'tx_bytes_total'],
                    false,
                );

                $isp->setAttribute('range_usage', $usage);

                return $isp;
            });
    }

    public function currentUserStats(): Collection
    {
        $cycle = $this->resolveCurrentCycleWithFreshSummaries();

        return MonitoredUser::query()
            ->where('is_active', true)
            ->where('queue_name', '!=', config('dashboard.group_totals_queue'))
            ->with([
                'monthlySummaries' => fn ($query) => $query->whereBelongsTo($cycle),
            ])
            ->orderBy('name')
            ->get();
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
        $groups = collect($this->groupDefinitions())->keyBy('label');

        if ($preset->key === 'cycle') {
            $summaries = MonthlyUserSummary::query()
                ->selectRaw('monitored_users.group_name, SUM(monthly_user_summaries.total_bytes) as total_bytes, COUNT(monthly_user_summaries.id) as user_count')
                ->join('monitored_users', 'monitored_users.id', '=', 'monthly_user_summaries.monitored_user_id')
                ->where('billing_cycle_id', $cycle->id)
                ->groupBy('monitored_users.group_name')
                ->get()
                ->keyBy('group_name');

            return $groups
                ->map(fn (array $group): array => [
                    'group_key' => $group['key'],
                    'group_name' => $group['label'],
                    'policy' => $group['policy'],
                    'subnets' => $group['subnets'],
                    'total_bytes' => (int) ($summaries[$group['label']]->total_bytes ?? 0),
                    'user_count' => (int) ($summaries[$group['label']]->user_count ?? 0),
                ])
                ->values()
                ->all();
        }

        $totals = $groups->mapWithKeys(fn (array $group): array => [$group['label'] => 0])->all();
        $counts = $groups->mapWithKeys(fn (array $group): array => [$group['label'] => 0])->all();

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

        return $groups
            ->map(fn (array $group): array => [
                'group_key' => $group['key'],
                'group_name' => $group['label'],
                'policy' => $group['policy'],
                'subnets' => $group['subnets'],
                'total_bytes' => $totals[$group['label']] ?? 0,
                'user_count' => $counts[$group['label']] ?? 0,
            ])
            ->values()
            ->all();
    }

    public function groupDefinitions(): array
    {
        return collect(config('dashboard.user_groups', []))
            ->map(fn (array $group): array => [
                'key' => $group['key'],
                'label' => $group['label'],
                'subnets' => $group['subnets'] ?? [],
                'policy' => $group['policy'] ?? [],
            ])
            ->values()
            ->all();
    }

    private function starlinkUsage(BillingCycle $cycle): array
    {
        $starlinkConfig = config('dashboard.isps.starlink', []);
        $isp = Isp::query()
            ->where('is_active', true)
            ->where('interface_name', $starlinkConfig['interface'] ?? '')
            ->first();
        $capBytes = (int) (($starlinkConfig['monthly_cap_gb'] ?? 0) * 1024 * 1024 * 1024);

        if (! $isp) {
            return [
                'label' => $starlinkConfig['label'] ?? 'Starlink',
                'used_bytes' => 0,
                'cap_bytes' => $capBytes,
                'usage_percent' => 0,
                'average_daily_bytes' => 0,
                'projected_monthly_bytes' => 0,
                'days_elapsed' => 0,
                'days_in_month' => 0,
                'daily_points' => [],
                'thresholds' => collect(config('dashboard.starlink_warning_thresholds', []))
                    ->map(fn (int $threshold): array => ['percent' => $threshold, 'reached' => false])
                    ->values()
                    ->all(),
            ];
        }

        $preset = $this->rangeService->resolve('cycle', $cycle);
        $usage = $this->snapshotUsageService->computeRangeUsage(
            $isp->snapshots(),
            $preset->start,
            $preset->end,
            ['rx_bytes_total', 'tx_bytes_total'],
            false,
        );
        $usedBytes = (int) $usage['total_bytes'];
        $timezone = config('dashboard.billing_cycle_timezone', config('app.timezone', 'UTC'));
        $now = now($timezone);
        $daysElapsed = max(1, (int) $now->day);
        $daysInMonth = (int) $now->daysInMonth;
        $averageDailyBytes = (int) round($usedBytes / $daysElapsed);
        $projectedMonthlyBytes = $averageDailyBytes * $daysInMonth;
        $usagePercent = $capBytes > 0 ? round(($usedBytes / $capBytes) * 100, 2) : 0.0;
        $dailyPoints = $this->dailyIspUsage($isp, $preset);

        return [
            'label' => $starlinkConfig['label'] ?? $isp->name,
            'used_bytes' => $usedBytes,
            'cap_bytes' => $capBytes,
            'usage_percent' => $usagePercent,
            'average_daily_bytes' => $averageDailyBytes,
            'projected_monthly_bytes' => $projectedMonthlyBytes,
            'days_elapsed' => $daysElapsed,
            'days_in_month' => $daysInMonth,
            'daily_points' => $dailyPoints,
            'thresholds' => collect(config('dashboard.starlink_warning_thresholds', []))
                ->map(fn (int $threshold): array => ['percent' => $threshold, 'reached' => $usagePercent >= $threshold])
                ->values()
                ->all(),
        ];
    }

    private function smartbroTotalUsage(BillingCycle $cycle): array
    {
        $preset = $this->rangeService->resolve('cycle', $cycle);
        $configs = collect(config('dashboard.isps', []))
            ->only(['smart_a', 'smart_b'])
            ->values();

        $items = $configs->map(function (array $config) use ($preset): array {
            $isp = Isp::query()
                ->where('is_active', true)
                ->where('interface_name', $config['interface'] ?? '')
                ->first();

            if (! $isp) {
                return [
                    'label' => $config['label'],
                    'used_bytes' => 0,
                ];
            }

            $usage = $this->snapshotUsageService->computeRangeUsage(
                $isp->snapshots(),
                $preset->start,
                $preset->end,
                ['rx_bytes_total', 'tx_bytes_total'],
                false,
            );

            return [
                'label' => $config['label'],
                'used_bytes' => (int) $usage['total_bytes'],
            ];
        })->values();

        return [
            'label' => 'SmartBro Total',
            'used_bytes' => (int) $items->sum('used_bytes'),
            'items' => $items->all(),
        ];
    }

    private function dailyIspUsage(Isp $isp, RangePreset $preset): array
    {
        $points = [];
        $previous = $isp->snapshots()
            ->where('recorded_at', '<', $preset->start)
            ->orderByDesc('recorded_at')
            ->first();

        $isp->snapshots()
            ->whereBetween('recorded_at', [$preset->start, $preset->end])
            ->orderBy('recorded_at')
            ->get()
            ->each(function (IspSnapshot $snapshot) use (&$points, &$previous): void {
                $bucket = CarbonImmutable::instance($snapshot->recorded_at)->startOfDay()->toDateString();
                $delta = $this->snapshotUsageService->sumPositiveDeltasFromSnapshots(
                    collect([$snapshot]),
                    ['rx_bytes_total', 'tx_bytes_total'],
                    $previous,
                    false,
                );

                if (! isset($points[$bucket])) {
                    $points[$bucket] = [
                        'date' => $bucket,
                        'total_bytes' => 0,
                    ];
                }

                $points[$bucket]['total_bytes'] += (int) (($delta['rx_bytes_total'] ?? 0) + ($delta['tx_bytes_total'] ?? 0));
                $previous = $snapshot;
            });

        return array_values($points);
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
        $this->usageAggregationService->aggregateCycle($cycle);

        return $cycle;
    }
}
