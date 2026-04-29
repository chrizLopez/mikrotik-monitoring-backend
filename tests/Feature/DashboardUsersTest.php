<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\MonitoredUser;
use App\Models\UserSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_endpoint_returns_monitored_users_with_summary_fields(): void
    {
        $cycle = BillingCycle::factory()->create(['is_current' => true]);
        Sanctum::actingAs(User::factory()->create());

        $monitoredUser = MonitoredUser::factory()->create([
            'name' => 'Home Router',
            'queue_name' => 'Home Router',
            'group_name' => 'Starlink Group',
        ]);

        UserSnapshot::factory()->create([
            'monitored_user_id' => $monitoredUser->id,
            'upload_bytes_total' => 100,
            'download_bytes_total' => 200,
            'total_bytes' => 333,
            'max_limit' => '2M/5M',
            'state' => 'NORMAL',
            'recorded_at' => $cycle->starts_at->copy()->addMinute(),
        ]);
        UserSnapshot::factory()->create([
            'monitored_user_id' => $monitoredUser->id,
            'upload_bytes_total' => 211,
            'download_bytes_total' => 422,
            'total_bytes' => 633,
            'max_limit' => '512k/2M',
            'state' => 'THROTTLED',
            'recorded_at' => $cycle->starts_at->copy()->addMinutes(2),
        ]);

        $this->getJson('/api/dashboard/users')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Home Router')
            ->assertJsonPath('data.0.total_bytes', 333)
            ->assertJsonPath('data.0.upload_bytes', 111)
            ->assertJsonPath('data.0.download_bytes', 222)
            ->assertJsonPath('data.0.remaining_bytes', 214748364467)
            ->assertJsonPath('data.0.usage_percent', 0.0)
            ->assertJsonPath('data.0.state', 'THROTTLED')
            ->assertJsonPath('data.0.current_max_limit', '512k/2M')
            ->assertJsonPath('data.0.group_name', 'Starlink Group');
    }
}
