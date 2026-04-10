<?php

namespace App\Services;

use App\Models\BillingCycle;
use App\Models\Isp;
use App\Models\MonthlyUserSummary;
use App\Models\MonitoredUser;
use App\Services\Support\SnapshotUsageService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class UsageAggregationService
{
    public function __construct(
        private readonly SnapshotUsageService $snapshotUsageService,
    ) {
    }

    public function aggregateCurrentCycle(): BillingCycle
    {
        $cycle = app(BillingCycleService::class)->resolveCurrent();
        $this->aggregateCycle($cycle);

        return $cycle;
    }

    public function aggregateCycle(BillingCycle $cycle): void
    {
        MonitoredUser::query()
            ->where('is_active', true)
            ->where('queue_name', '!=', config('dashboard.group_totals_queue'))
            ->orderBy('id')
            ->chunk(100, function (Collection $users) use ($cycle): void {
                foreach ($users as $user) {
                    $this->aggregateUser($user, $cycle);
                }
            });
    }

    public function aggregateUser(MonitoredUser $user, BillingCycle $cycle): MonthlyUserSummary
    {
        $cycleStart = CarbonImmutable::instance($cycle->starts_at);
        $rangeUsage = $this->snapshotUsageService->computeRangeUsage(
            $user->snapshots(),
            $cycleStart,
            CarbonImmutable::instance($cycle->ends_at),
            ['upload_bytes_total', 'download_bytes_total'],
        );
        $uploadBytes = $rangeUsage['totals']['upload_bytes_total'] ?? 0;
        $downloadBytes = $rangeUsage['totals']['download_bytes_total'] ?? 0;
        $totalBytes = $uploadBytes + $downloadBytes;
        $quotaBytes = $user->monthly_quota_bytes;
        $quotaState = $this->snapshotUsageService->computeQuotaState(
            $totalBytes,
            $quotaBytes,
            $rangeUsage['last_snapshot']?->state ?? 'NORMAL',
            $rangeUsage['last_snapshot']?->max_limit,
        );

        return MonthlyUserSummary::query()->updateOrCreate(
            [
                'monitored_user_id' => $user->id,
                'billing_cycle_id' => $cycle->id,
            ],
            [
                'upload_bytes' => $uploadBytes,
                'download_bytes' => $downloadBytes,
                'total_bytes' => $totalBytes,
                'quota_bytes' => $quotaState['quota_bytes'],
                'remaining_bytes' => $quotaState['remaining_bytes'],
                'usage_percent' => $quotaState['usage_percent'],
                'state' => $quotaState['state'] ?? 'NORMAL',
                'current_max_limit' => $quotaState['current_max_limit'],
                'last_snapshot_at' => $rangeUsage['last_snapshot']?->recorded_at,
            ],
        );
    }

    public function totalIspTrafficForCycle(BillingCycle $cycle): int
    {
        $start = CarbonImmutable::instance($cycle->starts_at);
        $end = CarbonImmutable::instance($cycle->ends_at);

        return Isp::query()->where('is_active', true)->get()->sum(function (Isp $isp) use ($start, $end): int {
            return $this->snapshotUsageService
                ->computeRangeUsage($isp->snapshots(), $start, $end, ['rx_bytes_total', 'tx_bytes_total'], false)['total_bytes'];
        });
    }
}
