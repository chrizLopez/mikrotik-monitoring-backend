<?php

namespace Tests\Unit;

use App\Services\Support\DeltaService;
use App\Services\Support\QuotaCalculator;
use App\Services\Support\SnapshotUsageService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class SnapshotUsageServiceTest extends TestCase
{
    public function test_sum_positive_deltas_from_snapshots_ignores_negative_resets_but_keeps_post_reset_values(): void
    {
        $service = new SnapshotUsageService(new DeltaService(), new QuotaCalculator());
        $snapshots = new Collection([
            (object) ['upload_bytes_total' => 100, 'download_bytes_total' => 200],
            (object) ['upload_bytes_total' => 500, 'download_bytes_total' => 700],
            (object) ['upload_bytes_total' => 50, 'download_bytes_total' => 70],
        ]);

        $totals = $service->sumPositiveDeltasFromSnapshots($snapshots, ['upload_bytes_total', 'download_bytes_total']);

        $this->assertSame(550, $totals['upload_bytes_total']);
        $this->assertSame(770, $totals['download_bytes_total']);
    }

    public function test_compute_remaining_bytes_never_returns_negative_values(): void
    {
        $service = new SnapshotUsageService(new DeltaService(), new QuotaCalculator());

        $this->assertSame(0, $service->computeRemainingBytes(500, 100));
    }
}
