<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\MonitoredUser;
use App\Models\UserSnapshot;
use App\Services\UsageAggregationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageAggregationTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregation_handles_counter_resets_safely(): void
    {
        $cycle = BillingCycle::factory()->create([
            'starts_at' => now()->startOfMonth(),
            'ends_at' => now()->startOfMonth()->addMonth(),
            'is_current' => true,
        ]);

        $user = MonitoredUser::factory()->create([
            'monthly_quota_bytes' => 2020,
        ]);

        UserSnapshot::factory()->create([
            'monitored_user_id' => $user->id,
            'upload_bytes_total' => 100,
            'download_bytes_total' => 200,
            'total_bytes' => 300,
            'recorded_at' => $cycle->starts_at->copy()->addMinute(),
        ]);

        UserSnapshot::factory()->create([
            'monitored_user_id' => $user->id,
            'upload_bytes_total' => 500,
            'download_bytes_total' => 700,
            'total_bytes' => 1200,
            'recorded_at' => $cycle->starts_at->copy()->addMinutes(2),
        ]);

        UserSnapshot::factory()->create([
            'monitored_user_id' => $user->id,
            'upload_bytes_total' => 50,
            'download_bytes_total' => 70,
            'total_bytes' => 120,
            'recorded_at' => $cycle->starts_at->copy()->addMinutes(3),
        ]);

        $summary = app(UsageAggregationService::class)->aggregateUser($user, $cycle);

        $this->assertSame(450, $summary->upload_bytes);
        $this->assertSame(570, $summary->download_bytes);
        $this->assertSame(1020, $summary->total_bytes);
        $this->assertSame(1000, $summary->remaining_bytes);
    }
}
