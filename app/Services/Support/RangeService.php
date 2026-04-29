<?php

namespace App\Services\Support;

use App\Models\BillingCycle;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class RangeService
{
    public function resolve(string $range, ?BillingCycle $cycle = null): RangePreset
    {
        $now = CarbonImmutable::now();

        return match ($range) {
            '10m' => new RangePreset('10m', $now->subMinutes(10), $now, '5m', 'Last 10 Minutes'),
            '1h' => new RangePreset('1h', $now->subHour(), $now, '5m', 'Last 1 Hour'),
            'today' => new RangePreset('today', $now->startOfDay(), $now, 'hour', 'Today'),
            '24h' => new RangePreset('24h', $now->subDay(), $now, '15m', 'Last 24 Hours'),
            '7d' => new RangePreset('7d', $now->subDays(7), $now, 'hour', 'Last 7 Days'),
            '30d' => new RangePreset('30d', $now->subDays(30), $now, 'day', 'Last 30 Days'),
            'cycle' => $this->resolveCycleRange($cycle),
            'prev_cycle' => $this->resolvePreviousCycleRange($cycle),
            default => throw new InvalidArgumentException('Unsupported range preset.'),
        };
    }

    public function bucketStart(CarbonImmutable $dateTime, string $bucket): CarbonImmutable
    {
        return match ($bucket) {
            '5m' => $dateTime->minute((int) floor($dateTime->minute / 5) * 5)->second(0),
            '15m' => $dateTime->minute((int) floor($dateTime->minute / 15) * 15)->second(0),
            'hour' => $dateTime->startOfHour(),
            'day' => $dateTime->startOfDay(),
            default => throw new InvalidArgumentException('Unsupported bucket interval.'),
        };
    }

    private function resolveCycleRange(?BillingCycle $cycle): RangePreset
    {
        if (! $cycle) {
            throw new InvalidArgumentException('Billing cycle range requires an active cycle.');
        }

        return new RangePreset(
            'cycle',
            CarbonImmutable::instance($cycle->starts_at),
            CarbonImmutable::instance($cycle->ends_at)->isPast() ? CarbonImmutable::instance($cycle->ends_at) : CarbonImmutable::now(),
            'day',
            $cycle->label,
        );
    }

    private function resolvePreviousCycleRange(?BillingCycle $cycle): RangePreset
    {
        if (! $cycle) {
            throw new InvalidArgumentException('Previous billing cycle range requires an active cycle.');
        }

        $previous = BillingCycle::query()
            ->where('starts_at', '<', $cycle->starts_at)
            ->orderByDesc('starts_at')
            ->first();

        if (! $previous) {
            return new RangePreset(
                'prev_cycle',
                CarbonImmutable::instance($cycle->starts_at)->subMonth(),
                CarbonImmutable::instance($cycle->starts_at),
                'day',
                'Previous Billing Cycle',
            );
        }

        return new RangePreset(
            'prev_cycle',
            CarbonImmutable::instance($previous->starts_at),
            CarbonImmutable::instance($previous->ends_at),
            'day',
            $previous->label,
        );
    }
}
