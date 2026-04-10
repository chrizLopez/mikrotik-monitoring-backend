<?php

namespace Tests\Unit;

use App\Models\BillingCycle;
use App\Services\Support\RangeService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class RangeServiceTest extends TestCase
{
    public function test_cycle_range_uses_current_billing_cycle_bounds(): void
    {
        $cycle = new BillingCycle([
            'starts_at' => CarbonImmutable::parse('2026-04-01 00:00:00'),
            'ends_at' => CarbonImmutable::parse('2026-05-01 00:00:00'),
            'label' => 'Apr 1 - Apr 30, 2026',
            'is_current' => true,
        ]);

        $preset = (new RangeService())->resolve('cycle', $cycle);

        $this->assertSame('cycle', $preset->key);
        $this->assertSame('day', $preset->bucket);
        $this->assertSame('Apr 1 - Apr 30, 2026', $preset->label);
    }

    public function test_bucket_start_rounds_to_hour(): void
    {
        $bucket = (new RangeService())->bucketStart(CarbonImmutable::parse('2026-04-04 13:47:25'), 'hour');

        $this->assertSame('2026-04-04 13:00:00', $bucket->format('Y-m-d H:i:s'));
    }
}
