<?php

namespace App\Services\Support;

class DeltaService
{
    public function safeDelta(?int $current, ?int $previous): int
    {
        if ($current === null) {
            return 0;
        }

        if ($previous === null) {
            return max(0, $current);
        }

        if ($current >= $previous) {
            return $current - $previous;
        }

        return max(0, $current);
    }

    public function deltaRate(?int $current, ?int $previous, float|int $seconds): ?int
    {
        if ($seconds <= 0 || $current === null) {
            return null;
        }

        return (int) round($this->safeDelta($current, $previous) * 8 / $seconds);
    }
}
