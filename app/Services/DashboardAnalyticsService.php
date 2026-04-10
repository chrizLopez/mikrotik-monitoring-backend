<?php

namespace App\Services;

use App\Models\Isp;
use App\Models\MonitoredUser;
use App\Models\UserSnapshot;
use App\Services\Support\RangePreset;
use App\Services\Support\RangeService;
use App\Services\Support\SnapshotUsageService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class DashboardAnalyticsService
{
    public function __construct(
        private readonly BillingCycleService $billingCycleService,
        private readonly DashboardService $dashboardService,
        private readonly RangeService $rangeService,
        private readonly SnapshotUsageService $snapshotUsageService,
    ) {
    }

    public function live(int $activeUserLimit = 5): array
    {
        return Cache::remember(sprintf('dashboard:live:%d', $activeUserLimit), now()->addSeconds(15), function () use ($activeUserLimit): array {
            $isps = $this->dashboardService->currentIspStats()->map(fn (Isp $isp): array => [
                'id' => $isp->id,
                'name' => $isp->name,
                'interface_name' => $isp->interface_name,
                'status' => $isp->status,
                'current_rx_bps' => (int) ($isp->snapshots->first()?->rx_bps ?? 0),
                'current_tx_bps' => (int) ($isp->snapshots->first()?->tx_bps ?? 0),
                'current_total_bps' => (int) (($isp->snapshots->first()?->rx_bps ?? 0) + ($isp->snapshots->first()?->tx_bps ?? 0)),
                'last_poll_timestamp' => $isp->snapshots->first()?->recorded_at?->toIso8601String(),
                'trend' => $isp->snapshots()
                    ->where('recorded_at', '>=', now()->subMinutes(10))
                    ->latest('recorded_at')
                    ->limit(12)
                    ->get()
                    ->reverse()
                    ->values()
                    ->map(fn ($snapshot): array => [
                        'timestamp' => $snapshot->recorded_at->toIso8601String(),
                        'rx_bps' => (int) ($snapshot->rx_bps ?? 0),
                        'tx_bps' => (int) ($snapshot->tx_bps ?? 0),
                        'total_bps' => (int) (($snapshot->rx_bps ?? 0) + ($snapshot->tx_bps ?? 0)),
                    ])->all(),
            ])->values();

            return [
                'isps' => $isps,
                'top_active_users' => $this->topActiveUsers($activeUserLimit),
            ];
        });
    }

    public function topActiveUsers(int $limit = 10): array
    {
        return MonitoredUser::query()
            ->where('is_active', true)
            ->where('queue_name', '!=', config('dashboard.group_totals_queue'))
            ->get()
            ->map(function (MonitoredUser $user): array {
                $snapshots = $user->snapshots()->latest('recorded_at')->limit(5)->get()->reverse()->values();
                $latest = $snapshots->last();
                $rate = $this->deriveCurrentRate($snapshots, ['download_bytes_total', 'upload_bytes_total']);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'group_name' => $user->group_name,
                    'subnet' => $user->subnet,
                    'download_bps' => $rate['download_bps'],
                    'upload_bps' => $rate['upload_bps'],
                    'combined_bps' => $rate['combined_bps'],
                    'current_max_limit' => $latest?->max_limit,
                    'state' => $latest?->state ?? 'NORMAL',
                    'last_snapshot_at' => $latest?->recorded_at?->toIso8601String(),
                ];
            })
            ->sortByDesc('combined_bps')
            ->take($limit)
            ->values()
            ->all();
    }

    public function topUsers(string $range, int $limit = 10): array
    {
        $preset = $this->resolveRange($range);

        return $this->topUsersForPreset($preset, $limit);
    }

    private function topUsersForPreset(RangePreset $preset, int $limit = 10): array
    {
        return MonitoredUser::query()
            ->where('is_active', true)
            ->where('queue_name', '!=', config('dashboard.group_totals_queue'))
            ->get()
            ->map(function (MonitoredUser $user) use ($preset): array {
                $usage = $this->snapshotUsageService->computeRangeUsage(
                    $user->snapshots(),
                    $preset->start,
                    $preset->end,
                    ['upload_bytes_total', 'download_bytes_total'],
                );
                $quota = $this->snapshotUsageService->computeQuotaState(
                    $usage['total_bytes'],
                    (int) $user->monthly_quota_bytes,
                    $usage['last_snapshot']?->state ?? 'NORMAL',
                    $usage['last_snapshot']?->max_limit,
                );
                $peak = $this->peakUserUsage($user, $preset);
                $currentRate = $this->deriveCurrentRate($user->snapshots()->latest('recorded_at')->limit(5)->get()->reverse()->values(), ['download_bytes_total', 'upload_bytes_total']);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'group_name' => $user->group_name,
                    'subnet' => $user->subnet,
                    'upload_bytes' => (int) ($usage['totals']['upload_bytes_total'] ?? 0),
                    'download_bytes' => (int) ($usage['totals']['download_bytes_total'] ?? 0),
                    'total_bytes' => (int) $usage['total_bytes'],
                    'remaining_quota_bytes' => (int) $quota['remaining_bytes'],
                    'quota_bytes' => (int) $quota['quota_bytes'],
                    'usage_percent' => (float) $quota['usage_percent'],
                    'state' => $quota['state'] ?? 'NORMAL',
                    'current_max_limit' => $quota['current_max_limit'],
                    'current_combined_bps' => $currentRate['combined_bps'],
                    'peak_combined_bps' => $peak['peak_combined_bps'],
                    'peak_at' => $peak['peak_at'],
                ];
            })
            ->sortByDesc('total_bytes')
            ->take($limit)
            ->values()
            ->all();
    }

    public function ispDistribution(string $range): array
    {
        return $this->ispDistributionForPreset($this->resolveRange($range));
    }

    public function comparisons(): array
    {
        $currentCycle = $this->resolveRange('cycle');
        $previousCycle = $this->resolveRange('prev_cycle');
        $today = $this->resolveRange('today');
        $yesterday = new RangePreset('yesterday', $today->start->subDay(), $today->start, $today->bucket, 'Yesterday');
        $current7d = $this->resolveRange('7d');
        $previous7d = new RangePreset('previous_7d', $current7d->start->subDays(7), $current7d->start, $current7d->bucket, 'Previous 7 Days');

        return [
            'today_vs_yesterday' => $this->comparisonBlock($today, $yesterday),
            'cycle_vs_previous_cycle' => $this->comparisonBlock($currentCycle, $previousCycle),
            'last_7d_vs_previous_7d' => $this->comparisonBlock($current7d, $previous7d),
        ];
    }

    public function alerts(string $range): array
    {
        $preset = $this->resolveRange($range);
        $quotaAlerts = collect($this->topUsers($range, 200))
            ->filter(fn (array $user): bool => $user['usage_percent'] >= 50)
            ->map(function (array $user): array {
                $threshold = $user['usage_percent'] >= 100 ? 100 : ($user['usage_percent'] >= 90 ? 90 : ($user['usage_percent'] >= 80 ? 80 : 50));

                return [
                    'type' => 'quota',
                    'severity' => $threshold >= 100 ? 'critical' : ($threshold >= 90 ? 'high' : ($threshold >= 80 ? 'medium' : 'low')),
                    'title' => "{$user['name']} crossed {$threshold}% quota usage",
                    'subject' => $user['name'],
                    'threshold' => $threshold,
                    'usage_percent' => $user['usage_percent'],
                    'total_bytes' => $user['total_bytes'],
                    'state' => $user['state'],
                ];
            })
            ->values();

        $healthAlerts = $this->healthSnapshotsAvailable()
            ? Isp::query()->where('is_active', true)->get()->map(function (Isp $isp) use ($preset): ?array {
            $latest = $isp->healthSnapshots()
                ->whereBetween('recorded_at', [$preset->start, $preset->end])
                ->latest('recorded_at')
                ->first();

            if (! $latest) {
                return null;
            }

            if ($latest->status === 'offline') {
                return [
                    'type' => 'health',
                    'severity' => 'critical',
                    'title' => "{$isp->name} is offline",
                    'subject' => $isp->name,
                    'latency_ms' => $latest->latency_ms,
                    'packet_loss_percent' => $latest->packet_loss_percent,
                    'status' => $latest->status,
                ];
            }

            if (($latest->packet_loss_percent ?? 0) >= 10 || ($latest->latency_ms ?? 0) >= 150) {
                return [
                    'type' => 'health',
                    'severity' => ($latest->packet_loss_percent ?? 0) >= 20 ? 'high' : 'medium',
                    'title' => "{$isp->name} has degraded quality",
                    'subject' => $isp->name,
                    'latency_ms' => $latest->latency_ms,
                    'packet_loss_percent' => $latest->packet_loss_percent,
                    'status' => $latest->status,
                ];
            }

            return null;
        })->filter()->values()
            : collect();

        $heavyUsage = collect($this->topActiveUsers(50))
            ->filter(function (array $user): bool {
                if ($user['combined_bps'] <= 0) {
                    return false;
                }

                $baseline = UserSnapshot::query()
                    ->where('monitored_user_id', $user['id'])
                    ->latest('recorded_at')
                    ->limit(12)
                    ->get()
                    ->reverse()
                    ->values();
                $derived = $this->deriveCurrentRate($baseline, ['download_bytes_total', 'upload_bytes_total']);

                return $user['combined_bps'] > max(1, $derived['combined_bps']) * 1.5;
            })
            ->map(fn (array $user): array => [
                'type' => 'usage',
                'severity' => 'medium',
                'title' => "{$user['name']} shows possible heavy usage",
                'subject' => $user['name'],
                'combined_bps' => $user['combined_bps'],
                'state' => $user['state'],
            ])
            ->values();

        return [
            'range' => $this->serializeRange($preset),
            'active_issues' => $quotaAlerts->whereIn('severity', ['critical', 'high'])->count() + $healthAlerts->whereIn('severity', ['critical', 'high'])->count(),
            'quota_alerts' => $quotaAlerts->all(),
            'health_alerts' => $healthAlerts->all(),
            'usage_alerts' => $heavyUsage->all(),
        ];
    }

    public function quotaTimeline(MonitoredUser $user, string $range): array
    {
        $preset = $this->resolveRange($range);
        $points = [];
        $runningTotal = 0;
        $previous = $user->snapshots()->where('recorded_at', '<', $preset->start)->latest('recorded_at')->first();

        $user->snapshots()
            ->whereBetween('recorded_at', [$preset->start, $preset->end])
            ->orderBy('recorded_at')
            ->get()
            ->each(function ($snapshot) use (&$points, &$runningTotal, &$previous, $preset): void {
                $bucket = $this->rangeService->bucketStart(CarbonImmutable::instance($snapshot->recorded_at), $preset->bucket)->toIso8601String();
                $delta = $this->snapshotUsageService->sumPositiveDeltasFromSnapshots(
                    collect([$snapshot]),
                    ['upload_bytes_total', 'download_bytes_total'],
                    $previous,
                );
                $uploadBytes = (int) ($delta['upload_bytes_total'] ?? 0);
                $downloadBytes = (int) ($delta['download_bytes_total'] ?? 0);
                $bucketTotal = $uploadBytes + $downloadBytes;
                $runningTotal += $bucketTotal;

                if (! isset($points[$bucket])) {
                    $points[$bucket] = [
                        'timestamp' => $bucket,
                        'upload_bytes' => 0,
                        'download_bytes' => 0,
                        'total_bytes' => 0,
                        'cumulative_bytes' => 0,
                        'state' => $snapshot->state,
                    ];
                }

                $points[$bucket]['upload_bytes'] += $uploadBytes;
                $points[$bucket]['download_bytes'] += $downloadBytes;
                $points[$bucket]['total_bytes'] += $bucketTotal;
                $points[$bucket]['cumulative_bytes'] = $runningTotal;
                $points[$bucket]['state'] = $snapshot->state;
                $previous = $snapshot;
            });

        $quota = $this->snapshotUsageService->computeQuotaState($runningTotal, (int) $user->monthly_quota_bytes);

        return [
            'range' => $this->serializeRange($preset),
            'summary' => [
                'used_bytes' => $runningTotal,
                'remaining_bytes' => (int) $quota['remaining_bytes'],
                'quota_bytes' => (int) $quota['quota_bytes'],
                'usage_percent' => (float) $quota['usage_percent'],
            ],
            'points' => array_values($points),
        ];
    }

    public function throttlingHistory(string $range, ?MonitoredUser $user = null): array
    {
        $preset = $this->resolveRange($range);
        $users = $user ? collect([$user]) : MonitoredUser::query()
            ->where('is_active', true)
            ->where('queue_name', '!=', config('dashboard.group_totals_queue'))
            ->get();

        $items = $users->map(function (MonitoredUser $entry) use ($preset): array {
            $snapshots = $entry->snapshots()
                ->whereBetween('recorded_at', [$preset->start, $preset->end])
                ->orderBy('recorded_at')
                ->get();
            $transitions = [];
            $previousState = null;

            foreach ($snapshots as $snapshot) {
                if ($previousState !== null && $snapshot->state !== $previousState) {
                    $transitions[] = [
                        'from_state' => $previousState,
                        'to_state' => $snapshot->state,
                        'changed_at' => $snapshot->recorded_at->toIso8601String(),
                    ];
                }

                $previousState = $snapshot->state;
            }

            return [
                'id' => $entry->id,
                'name' => $entry->name,
                'group_name' => $entry->group_name,
                'current_state' => $snapshots->last()?->state ?? 'NORMAL',
                'last_state_change' => collect($transitions)->last()['changed_at'] ?? null,
                'throttled_events' => collect($transitions)->where('to_state', 'THROTTLED')->count(),
                'transitions' => $transitions,
            ];
        })->values();

        return [
            'range' => $this->serializeRange($preset),
            'items' => $items->all(),
        ];
    }

    public function healthHistory(Isp $isp, string $range): array
    {
        $preset = $this->resolveRange($range);
        if (! $this->healthSnapshotsAvailable()) {
            return [
                'range' => $this->serializeRange($preset),
                'latest' => [
                    'latency_ms' => null,
                    'packet_loss_percent' => null,
                    'jitter_ms' => null,
                    'status' => 'unknown',
                    'recorded_at' => null,
                ],
                'averages' => [
                    'latency_ms' => null,
                    'packet_loss_percent' => null,
                ],
                'outages' => [
                    'count' => 0,
                    'total_downtime_minutes' => 0,
                    'items' => [],
                ],
                'points' => [],
            ];
        }

        $points = $isp->healthSnapshots()
            ->whereBetween('recorded_at', [$preset->start, $preset->end])
            ->orderBy('recorded_at')
            ->get()
            ->map(fn ($snapshot): array => [
                'timestamp' => $snapshot->recorded_at->toIso8601String(),
                'latency_ms' => $snapshot->latency_ms,
                'packet_loss_percent' => $snapshot->packet_loss_percent,
                'jitter_ms' => $snapshot->jitter_ms,
                'status' => $snapshot->status,
                'ping_target' => $snapshot->ping_target,
            ])
            ->all();
        $outages = $this->outagesForIsp($isp, $preset);
        $latest = $isp->healthSnapshots()->latest('recorded_at')->first();

        return [
            'range' => $this->serializeRange($preset),
            'latest' => [
                'latency_ms' => $latest?->latency_ms,
                'packet_loss_percent' => $latest?->packet_loss_percent,
                'jitter_ms' => $latest?->jitter_ms,
                'status' => $latest?->status ?? 'unknown',
                'recorded_at' => $latest?->recorded_at?->toIso8601String(),
            ],
            'averages' => [
                'latency_ms' => round((float) $isp->healthSnapshots()->whereBetween('recorded_at', [$preset->start, $preset->end])->avg('latency_ms'), 2),
                'packet_loss_percent' => round((float) $isp->healthSnapshots()->whereBetween('recorded_at', [$preset->start, $preset->end])->avg('packet_loss_percent'), 2),
            ],
            'outages' => $outages,
            'points' => $points,
        ];
    }

    public function reports(string $range): array
    {
        return [
            'top_users' => $this->topUsers($range, 10),
            'isp_distribution' => $this->ispDistribution($range),
            'group_usage' => $this->dashboardService->groupUsage($range),
            'comparisons' => $this->comparisons(),
            'alerts' => $this->alerts($range),
        ];
    }

    private function comparisonBlock(RangePreset $current, RangePreset $previous): array
    {
        $currentTopUsers = collect($this->topUsersForPreset($current, 5));
        $previousTopUsers = collect($this->topUsersForPreset($previous, 5));
        $currentIsp = $this->ispDistributionForPreset($current);
        $previousIsp = $this->ispDistributionForPreset($previous);
        $currentGroups = collect($this->groupUsageForPreset($current));
        $previousGroups = collect($this->groupUsageForPreset($previous));

        return [
            'current_label' => $current->label,
            'previous_label' => $previous->label,
            'total_isp_traffic' => $this->diffBlock($currentIsp['total_bytes'], $previousIsp['total_bytes']),
            'total_user_traffic' => $this->diffBlock((int) $currentTopUsers->sum('total_bytes'), (int) $previousTopUsers->sum('total_bytes')),
            'top_users' => $currentTopUsers->map(function (array $user) use ($previousTopUsers): array {
                $previous = $previousTopUsers->firstWhere('name', $user['name']);

                return [
                    'name' => $user['name'],
                    'current_total_bytes' => $user['total_bytes'],
                    'previous_total_bytes' => (int) ($previous['total_bytes'] ?? 0),
                    'change_percent' => $this->percentChange($user['total_bytes'], (int) ($previous['total_bytes'] ?? 0)),
                ];
            })->values()->all(),
            'group_usage' => $currentGroups->map(function (array $group) use ($previousGroups): array {
                $previous = $previousGroups->firstWhere('group_name', $group['group_name']);

                return [
                    'group_name' => $group['group_name'],
                    'current_total_bytes' => $group['total_bytes'],
                    'previous_total_bytes' => (int) ($previous['total_bytes'] ?? 0),
                    'change_percent' => $this->percentChange((int) $group['total_bytes'], (int) ($previous['total_bytes'] ?? 0)),
                ];
            })->values()->all(),
        ];
    }

    private function diffBlock(int $current, int $previous): array
    {
        return [
            'current' => $current,
            'previous' => $previous,
            'change_percent' => $this->percentChange($current, $previous),
        ];
    }

    private function deriveCurrentRate(Collection $snapshots, array $fields): array
    {
        if ($snapshots->count() < 2) {
            return ['download_bps' => 0, 'upload_bps' => 0, 'combined_bps' => 0];
        }

        $pairs = $snapshots->values()->zip($snapshots->slice(1)->values())->filter(fn ($pair): bool => $pair[1] !== null);
        $downloadRates = [];
        $uploadRates = [];

        foreach ($pairs as [$previous, $current]) {
            $seconds = max(1, CarbonImmutable::instance($previous->recorded_at)->diffInSeconds(CarbonImmutable::instance($current->recorded_at)));
            $downloadDelta = max(0, (int) (($current->{$fields[0]} ?? 0) - ($previous->{$fields[0]} ?? 0)));
            $uploadDelta = max(0, (int) (($current->{$fields[1]} ?? 0) - ($previous->{$fields[1]} ?? 0)));
            $downloadRates[] = (int) round(($downloadDelta * 8) / $seconds);
            $uploadRates[] = (int) round(($uploadDelta * 8) / $seconds);
        }

        $download = (int) round(collect($downloadRates)->avg() ?? 0);
        $upload = (int) round(collect($uploadRates)->avg() ?? 0);

        return [
            'download_bps' => $download,
            'upload_bps' => $upload,
            'combined_bps' => $download + $upload,
        ];
    }

    private function peakUserUsage(MonitoredUser $user, RangePreset $preset): array
    {
        $snapshots = $user->snapshots()
            ->whereBetween('recorded_at', [$preset->start, $preset->end])
            ->orderBy('recorded_at')
            ->get();
        $peak = ['peak_combined_bps' => 0, 'peak_at' => null];
        $previous = null;

        foreach ($snapshots as $snapshot) {
            if ($previous) {
                $seconds = max(1, $previous->recorded_at->diffInSeconds($snapshot->recorded_at));
                $download = max(0, (int) $snapshot->download_bytes_total - (int) $previous->download_bytes_total);
                $upload = max(0, (int) $snapshot->upload_bytes_total - (int) $previous->upload_bytes_total);
                $combined = (int) round((($download + $upload) * 8) / $seconds);

                if ($combined > $peak['peak_combined_bps']) {
                    $peak = [
                        'peak_combined_bps' => $combined,
                        'peak_at' => $snapshot->recorded_at->toIso8601String(),
                    ];
                }
            }

            $previous = $snapshot;
        }

        return $peak;
    }

    private function outagesForIsp(Isp $isp, RangePreset $preset): array
    {
        if (! $this->healthSnapshotsAvailable()) {
            return [
                'count' => 0,
                'total_downtime_minutes' => 0,
                'items' => [],
            ];
        }

        $snapshots = $isp->healthSnapshots()
            ->whereBetween('recorded_at', [$preset->start, $preset->end])
            ->orderBy('recorded_at')
            ->get();
        $outages = [];
        $startedAt = null;

        foreach ($snapshots as $snapshot) {
            if ($snapshot->status === 'offline' && $startedAt === null) {
                $startedAt = $snapshot->recorded_at;
            }

            if ($snapshot->status !== 'offline' && $startedAt !== null) {
                $outages[] = [
                    'started_at' => $startedAt->toIso8601String(),
                    'ended_at' => $snapshot->recorded_at->toIso8601String(),
                    'duration_minutes' => max(1, $startedAt->diffInMinutes($snapshot->recorded_at)),
                ];
                $startedAt = null;
            }
        }

        if ($startedAt !== null) {
            $outages[] = [
                'started_at' => $startedAt->toIso8601String(),
                'ended_at' => null,
                'duration_minutes' => max(1, $startedAt->diffInMinutes(now())),
            ];
        }

        return [
            'count' => count($outages),
            'total_downtime_minutes' => (int) collect($outages)->sum('duration_minutes'),
            'items' => $outages,
        ];
    }

    private function resolveRange(string $range): RangePreset
    {
        return $this->rangeService->resolve($range, $this->billingCycleService->resolveCurrent());
    }

    private function ispDistributionForPreset(RangePreset $preset): array
    {
        $items = Isp::query()
            ->where('is_active', true)
            ->orderByRaw('display_order is null')
            ->orderBy('display_order')
            ->get()
            ->map(function (Isp $isp) use ($preset): array {
                $usage = $this->snapshotUsageService->computeRangeUsage(
                    $isp->snapshots(),
                    $preset->start,
                    $preset->end,
                    ['rx_bytes_total', 'tx_bytes_total'],
                    false,
                );

                return [
                    'id' => $isp->id,
                    'name' => $isp->name,
                    'interface_name' => $isp->interface_name,
                    'download_bytes' => (int) ($usage['totals']['rx_bytes_total'] ?? 0),
                    'upload_bytes' => (int) ($usage['totals']['tx_bytes_total'] ?? 0),
                    'total_bytes' => (int) $usage['total_bytes'],
                ];
            })
            ->values();
        $total = max(1, (int) $items->sum('total_bytes'));

        return [
            'range' => $this->serializeRange($preset),
            'items' => $items->map(fn (array $item): array => $item + [
                'share_percent' => round(($item['total_bytes'] / $total) * 100, 2),
            ])->all(),
            'total_bytes' => (int) $items->sum('total_bytes'),
        ];
    }

    private function groupUsageForPreset(RangePreset $preset): array
    {
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

    private function percentChange(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return $current === 0 ? 0.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    private function healthSnapshotsAvailable(): bool
    {
        return Schema::hasTable('isp_health_snapshots');
    }
}
