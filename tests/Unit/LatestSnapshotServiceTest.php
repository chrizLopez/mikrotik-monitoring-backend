<?php

namespace Tests\Unit;

use App\Models\Isp;
use App\Models\IspSnapshot;
use App\Models\MonitoredUser;
use App\Models\UserSnapshot;
use App\Services\LatestSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LatestSnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_only_latest_snapshot_per_user_and_isp(): void
    {
        $service = app(LatestSnapshotService::class);
        $user = MonitoredUser::factory()->create();
        $isp = Isp::factory()->create();

        UserSnapshot::factory()->create([
            'monitored_user_id' => $user->id,
            'download_bytes_total' => 100,
            'recorded_at' => now()->subMinutes(2),
        ]);
        UserSnapshot::factory()->create([
            'monitored_user_id' => $user->id,
            'download_bytes_total' => 300,
            'recorded_at' => now()->subMinute(),
        ]);

        IspSnapshot::factory()->create([
            'isp_id' => $isp->id,
            'rx_bytes_total' => 500,
            'recorded_at' => now()->subMinutes(2),
        ]);
        IspSnapshot::factory()->create([
            'isp_id' => $isp->id,
            'rx_bytes_total' => 900,
            'recorded_at' => now()->subMinute(),
        ]);

        $this->assertCount(1, $service->latestUserSnapshots());
        $this->assertSame(300, $service->latestUserSnapshots()->first()->download_bytes_total);
        $this->assertCount(1, $service->latestIspSnapshots());
        $this->assertSame(900, $service->latestIspSnapshots()->first()->rx_bytes_total);
    }
}
