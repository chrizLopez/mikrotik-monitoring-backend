<?php

namespace Tests\Unit;

use App\Services\BillingCycleService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class BillingCycleServiceTest extends TestCase
{
    public function test_cycle_boundaries_default_to_the_23rd_of_each_month(): void
    {
        config()->set('dashboard.billing_cycle_day', 23);
        config()->set('dashboard.billing_cycle_timezone', 'Asia/Manila');

        [$startsAt, $endsAt, $label] = app(BillingCycleService::class)->cycleBoundaries(
            CarbonImmutable::parse('2026-04-11 12:00:00', 'Asia/Manila')
        );

        $this->assertSame('2026-03-23 00:00:00', $startsAt->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-23 00:00:00', $endsAt->format('Y-m-d H:i:s'));
        $this->assertSame('Mar 23 - Apr 22, 2026', $label);
    }

    public function test_cycle_rolls_forward_on_the_23rd(): void
    {
        config()->set('dashboard.billing_cycle_day', 23);
        config()->set('dashboard.billing_cycle_timezone', 'Asia/Manila');

        [$startsAt, $endsAt, $label] = app(BillingCycleService::class)->cycleBoundaries(
            CarbonImmutable::parse('2026-04-23 08:00:00', 'Asia/Manila')
        );

        $this->assertSame('2026-04-23 00:00:00', $startsAt->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-23 00:00:00', $endsAt->format('Y-m-d H:i:s'));
        $this->assertSame('Apr 23 - May 22, 2026', $label);
    }
}
