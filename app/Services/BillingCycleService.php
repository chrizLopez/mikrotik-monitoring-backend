<?php

namespace App\Services;

use App\Models\BillingCycle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class BillingCycleService
{
    public function resolveCurrent(?CarbonImmutable $now = null): BillingCycle
    {
        $now ??= CarbonImmutable::now(config('dashboard.billing_cycle_timezone'));
        [$startsAt, $endsAt, $label] = $this->cycleBoundaries($now);

        return DB::transaction(function () use ($startsAt, $endsAt, $label): BillingCycle {
            $startsAtUtc = $startsAt->utc();
            $endsAtUtc = $endsAt->utc();

            $currentCycle = BillingCycle::query()
                ->where('is_current', true)
                ->lockForUpdate()
                ->first();

            if (
                $currentCycle !== null
                && $currentCycle->starts_at->eq($startsAtUtc)
                && $currentCycle->ends_at->eq($endsAtUtc)
            ) {
                if ($currentCycle->label !== $label) {
                    $currentCycle->forceFill(['label' => $label])->save();
                }

                return $currentCycle;
            }

            $targetCycle = BillingCycle::query()
                ->where('starts_at', $startsAtUtc)
                ->where('ends_at', $endsAtUtc)
                ->lockForUpdate()
                ->first();

            if ($currentCycle !== null && $currentCycle->id !== $targetCycle?->id) {
                $currentCycle->forceFill(['is_current' => false])->save();
            }

            if ($targetCycle !== null) {
                if (! $targetCycle->is_current || $targetCycle->label !== $label) {
                    $targetCycle->forceFill([
                        'label' => $label,
                        'is_current' => true,
                    ])->save();
                }

                return $targetCycle;
            }

            return BillingCycle::query()->create([
                'starts_at' => $startsAtUtc,
                'ends_at' => $endsAtUtc,
                'label' => $label,
                'is_current' => true,
            ]);
        }, 5);
    }

    public function cycleBoundaries(?CarbonImmutable $now = null): array
    {
        $timezone = config('dashboard.billing_cycle_timezone');
        $cycleDay = max(1, min(28, (int) config('dashboard.billing_cycle_day', 1)));
        $now ??= CarbonImmutable::now($timezone);

        $currentMonthStart = $now->day >= $cycleDay
            ? $now->startOfMonth()->day($cycleDay)->startOfDay()
            : $now->subMonthNoOverflow()->startOfMonth()->day($cycleDay)->startOfDay();

        $nextMonthStart = $currentMonthStart->addMonthNoOverflow();
        $label = $currentMonthStart->format('M j').' - '.$nextMonthStart->subSecond()->format('M j, Y');

        return [$currentMonthStart, $nextMonthStart, $label];
    }
}
