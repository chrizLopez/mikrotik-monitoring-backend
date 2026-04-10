<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\Isp;
use App\Models\IspSnapshot;
use App\Models\MonitoredUser;
use App\Models\RouteStatusSnapshot;
use App\Models\UserSnapshot;
use App\Models\User;
use App\Services\UsageAggregationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_endpoint_returns_expected_metrics(): void
    {
        $cycle = BillingCycle::factory()->create(['is_current' => true]);
        Sanctum::actingAs(User::factory()->create());

        $isp = Isp::factory()->create(['is_active' => true]);
        IspSnapshot::factory()->create([
            'isp_id' => $isp->id,
            'rx_bytes_total' => 1000,
            'tx_bytes_total' => 2000,
            'recorded_at' => $cycle->starts_at->copy()->addMinute(),
        ]);
        IspSnapshot::factory()->create([
            'isp_id' => $isp->id,
            'rx_bytes_total' => 5000,
            'tx_bytes_total' => 9000,
            'recorded_at' => $cycle->starts_at->copy()->addMinutes(2),
        ]);
        RouteStatusSnapshot::query()->create([
            'isp_id' => $isp->id,
            'status' => 'online',
            'details' => [],
            'recorded_at' => $cycle->starts_at->copy()->addMinutes(2),
        ]);

        $monitoredUser = MonitoredUser::factory()->create();
        UserSnapshot::factory()->create([
            'monitored_user_id' => $monitoredUser->id,
            'upload_bytes_total' => 1000,
            'download_bytes_total' => 2000,
            'total_bytes' => 3000,
            'recorded_at' => $cycle->starts_at->copy()->addMinute(),
        ]);
        UserSnapshot::factory()->create([
            'monitored_user_id' => $monitoredUser->id,
            'upload_bytes_total' => 41000,
            'download_bytes_total' => 85456,
            'total_bytes' => 126456,
            'state' => 'THROTTLED',
            'recorded_at' => $cycle->starts_at->copy()->addMinutes(2),
        ]);

        $this->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.range', 'cycle')
            ->assertJsonPath('data.total_monitored_users', 1)
            ->assertJsonPath('data.throttled_user_count', 1)
            ->assertJsonPath('data.active_isp_count', 1)
            ->assertJsonPath('data.total_isp_traffic_this_cycle', 11000)
            ->assertJsonPath('data.total_user_traffic_this_cycle', 126456)
            ->assertJsonPath('data.total_isp_traffic_for_range', 11000)
            ->assertJsonPath('data.total_user_traffic_for_range', 126456);
    }

    public function test_summary_endpoint_reuses_short_cache_for_cycle_aggregation(): void
    {
        $cycle = BillingCycle::factory()->create(['is_current' => true]);
        Sanctum::actingAs(User::factory()->create());

        $mock = Mockery::mock(UsageAggregationService::class);
        $mock->shouldReceive('aggregateCycle')->once()->withAnyArgs();
        $mock->shouldReceive('totalIspTrafficForCycle')->once()->andReturn(0);
        $this->app->instance(UsageAggregationService::class, $mock);

        $this->getJson('/api/dashboard/summary')->assertOk();
        $this->getJson('/api/dashboard/summary')->assertOk();
    }
}
