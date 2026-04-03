<?php

namespace App\Services\Support;

class QuotaCalculator
{
    public function usagePercent(int $usedBytes, int $quotaBytes): float
    {
        if ($quotaBytes <= 0) {
            return 0.0;
        }

        return round(min(100, ($usedBytes / $quotaBytes) * 100), 2);
    }

    public function remainingBytes(int $usedBytes, int $quotaBytes): int
    {
        return max(0, $quotaBytes - $usedBytes);
    }
}
