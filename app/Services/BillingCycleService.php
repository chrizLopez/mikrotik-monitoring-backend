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

        return DB::transaction(function () use ($now): BillingCycle {
            BillingCycle::query()->where('is_current', true)->update(['is_current' => false]);

            [$startsAt, $endsAt, $label] = $this->cycleBoundaries($now);

            return BillingCycle::query()->updateOrCreate(
                ['starts_at' => $startsAt->utc(), 'ends_at' => $endsAt->utc()],
                ['label' => $label, 'is_current' => true],
            );
        });
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
