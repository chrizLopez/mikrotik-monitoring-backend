<?php

namespace App\Services\Mikrotik;

class CounterDeltaCalculator
{
    public function calculateBps(?int $currentBytes, ?int $previousBytes, int|float $elapsedSeconds): ?int
    {
        if ($currentBytes === null || $previousBytes === null || $elapsedSeconds <= 0) {
            return null;
        }

        $deltaBytes = $currentBytes - $previousBytes;

        if ($deltaBytes < 0) {
            return null;
        }

        return (int) floor(($deltaBytes * 8) / $elapsedSeconds);
    }
}
