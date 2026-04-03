<?php

namespace Tests\Unit;

use App\Services\Mikrotik\CounterDeltaCalculator;
use PHPUnit\Framework\TestCase;

class CounterDeltaCalculatorTest extends TestCase
{
    public function test_it_calculates_bits_per_second_from_deltas(): void
    {
        $calculator = new CounterDeltaCalculator();

        $bps = $calculator->calculateBps(2000, 1000, 10);

        $this->assertSame(800, $bps);
    }

    public function test_it_returns_null_when_counters_reset(): void
    {
        $calculator = new CounterDeltaCalculator();

        $bps = $calculator->calculateBps(100, 1000, 10);

        $this->assertNull($bps);
    }
}
