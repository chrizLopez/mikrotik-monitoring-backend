<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\Isp;
use App\Models\IspSnapshot;
use App\Models\MonitoredUser;
use App\Models\User;
use App\Models\UserSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardGroupsAndIspsTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_usage_uses_positive_deltas_instead_of_summing_cumulative_totals(): void
    {
        $cycle = BillingCycle::factory()->create(['is_current' => true]);
        Sanctum::actingAs(User::factory()->create());

        $groupA = MonitoredUser::factory()->create(['group_name' => 'Starlink Group']);
        $groupB = MonitoredUser::factory()->create(['group_name' => 'Smart Group']);

        UserSnapshot::factory()->create([
            'monitored_user_id' => $groupA->id,
            'upload_bytes_total' => 100,
            'download_bytes_total' => 200,
            'total_bytes' => 300,
            'recorded_at' => $cycle->starts_at->copy()->addMinute(),
        ]);
        UserSnapshot::factory()->create([
            'monitored_user_id' => $groupA->id,
            'upload_bytes_total' => 1100,
            'download_bytes_total' => 2200,
            'total_bytes' => 3300,
            'recorded_at' => $cycle->starts_at->copy()->addMinutes(2),
        ]);
        UserSnapshot::factory()->create([
            'monitored_user_id' => $groupB->id,
            'upload_bytes_total' => 1000,
            'download_bytes_total' => 2000,
            'total_bytes' => 3000,
            'recorded_at' => $cycle->starts_at->copy()->addMinute(),
        ]);
        UserSnapshot::factory()->create([
            'monitored_user_id' => $groupB->id,
            'upload_bytes_total' => 1500,
            'download_bytes_total' => 2600,
            'total_bytes' => 4100,
            'recorded_at' => $cycle->starts_at->copy()->addMinutes(2),
        ]);

        $this->getJson('/api/dashboard/groups/usage?range=cycle')
            ->assertOk()
            ->assertJsonPath('data.0.group_name', 'Starlink Group')
            ->assertJsonPath('data.0.total_bytes', 3300)
            ->assertJsonPath('data.1.group_name', 'Smart Group')
            ->assertJsonPath('data.1.total_bytes', 4100);
    }

    public function test_isp_history_returns_delta_based_totals_for_selected_range(): void
    {
        $cycle = BillingCycle::factory()->create(['is_current' => true]);
        Sanctum::actingAs(User::factory()->create());

        $isp = Isp::factory()->create(['interface_name' => 'ether1 - Starlink']);

        IspSnapshot::factory()->create([
            'isp_id' => $isp->id,
            'rx_bytes_total' => 100,
            'tx_bytes_total' => 200,
            'rx_bps' => 1000,
            'tx_bps' => 2000,
            'recorded_at' => $cycle->starts_at->copy()->addMinute(),
        ]);
        IspSnapshot::factory()->create([
            'isp_id' => $isp->id,
            'rx_bytes_total' => 600,
            'tx_bytes_total' => 900,
            'rx_bps' => 2000,
            'tx_bps' => 3000,
            'recorded_at' => $cycle->starts_at->copy()->addMinutes(2),
        ]);

        $this->getJson('/api/dashboard/isps/ether1%20-%20Starlink/history?range=cycle')
            ->assertOk()
            ->assertJsonPath('data.totals.download_bytes', 600)
            ->assertJsonPath('data.totals.upload_bytes', 900)
            ->assertJsonPath('data.totals.total_bytes', 1500);
    }
}
