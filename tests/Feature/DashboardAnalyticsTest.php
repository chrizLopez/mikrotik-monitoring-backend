<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\Isp;
use App\Models\IspHealthSnapshot;
use App\Models\IspSnapshot;
use App\Models\MonitoredUser;
use App\Models\User;
use App\Models\UserSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_endpoint_returns_isp_and_active_user_sections(): void
    {
        $cycle = BillingCycle::factory()->create(['is_current' => true]);
        Sanctum::actingAs(User::factory()->create());
        $isp = Isp::factory()->create(['name' => 'Old Starlink', 'interface_name' => 'ether1']);
        $user = MonitoredUser::factory()->create(['name' => 'Home Router']);

        IspSnapshot::factory()->create([
            'isp_id' => $isp->id,
            'rx_bps' => 1000,
            'tx_bps' => 2000,
            'rx_bytes_total' => 1000,
            'tx_bytes_total' => 2000,
            'recorded_at' => $cycle->starts_at->copy()->addMinute(),
        ]);
        IspSnapshot::factory()->create([
            'isp_id' => $isp->id,
            'rx_bps' => 3000,
            'tx_bps' => 4000,
            'rx_bytes_total' => 3000,
            'tx_bytes_total' => 5000,
            'recorded_at' => $cycle->starts_at->copy()->addMinutes(2),
        ]);
        UserSnapshot::factory()->create([
            'monitored_user_id' => $user->id,
            'upload_bytes_total' => 1000,
            'download_bytes_total' => 2000,
            'recorded_at' => $cycle->starts_at->copy()->addMinute(),
        ]);
        UserSnapshot::factory()->create([
            'monitored_user_id' => $user->id,
            'upload_bytes_total' => 4000,
            'download_bytes_total' => 8000,
            'recorded_at' => $cycle->starts_at->copy()->addMinutes(2),
        ]);

        $this->getJson('/api/dashboard/live')
            ->assertOk()
            ->assertJsonPath('data.isps.0.name', 'Old Starlink')
            ->assertJsonPath('data.top_active_users.0.name', 'Home Router');
    }

    public function test_distribution_and_alert_endpoints_return_derived_metrics(): void
    {
        $cycle = BillingCycle::factory()->create(['is_current' => true]);
        Sanctum::actingAs(User::factory()->create());
        $isp = Isp::factory()->create(['name' => 'New Starlink', 'interface_name' => 'ether2']);
        $user = MonitoredUser::factory()->create(['name' => 'VLAN20 - Camaymayan']);

        IspSnapshot::factory()->create([
            'isp_id' => $isp->id,
            'rx_bytes_total' => 5000,
            'tx_bytes_total' => 9000,
            'recorded_at' => $cycle->starts_at->copy()->addMinutes(2),
        ]);
        IspHealthSnapshot::factory()->create([
            'isp_id' => $isp->id,
            'status' => 'offline',
            'latency_ms' => null,
            'packet_loss_percent' => 100,
            'recorded_at' => now(),
        ]);
        UserSnapshot::factory()->create([
            'monitored_user_id' => $user->id,
            'upload_bytes_total' => 150 * 1024 * 1024 * 1024,
            'download_bytes_total' => 80 * 1024 * 1024 * 1024,
            'state' => 'THROTTLED',
            'recorded_at' => now(),
        ]);

        $this->getJson('/api/dashboard/isps/distribution?range=cycle')
            ->assertOk()
            ->assertJsonPath('data.items.0.name', 'New Starlink');

        $this->getJson('/api/dashboard/alerts?range=cycle')
            ->assertOk()
            ->assertJsonPath('data.health_alerts.0.subject', 'New Starlink');
    }

    public function test_quota_timeline_and_throttling_history_are_derived_from_snapshots(): void
    {
        $cycle = BillingCycle::factory()->create(['is_current' => true]);
        Sanctum::actingAs(User::factory()->create());
        $user = MonitoredUser::factory()->create(['name' => 'VLAN30 - Rutor']);

        UserSnapshot::factory()->create([
            'monitored_user_id' => $user->id,
            'upload_bytes_total' => 1000,
            'download_bytes_total' => 2000,
            'state' => 'NORMAL',
            'recorded_at' => $cycle->starts_at->copy()->addMinute(),
        ]);
        UserSnapshot::factory()->create([
            'monitored_user_id' => $user->id,
            'upload_bytes_total' => 4000,
            'download_bytes_total' => 8000,
            'state' => 'THROTTLED',
            'recorded_at' => $cycle->starts_at->copy()->addMinutes(5),
        ]);

        $this->getJson("/api/dashboard/users/{$user->id}/quota-timeline?range=cycle")
            ->assertOk()
            ->assertJsonPath('data.summary.used_bytes', 12000);

        $this->getJson("/api/dashboard/users/{$user->id}/throttling-history?range=cycle")
            ->assertOk()
            ->assertJsonPath('data.items.0.throttled_events', 1);
    }
}
