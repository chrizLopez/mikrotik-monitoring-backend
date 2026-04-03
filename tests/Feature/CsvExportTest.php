<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\MonitoredUser;
use App\Models\MonthlyUserSummary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CsvExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_csv_export_downloads_summary_rows(): void
    {
        $cycle = BillingCycle::factory()->create(['is_current' => true]);
        Sanctum::actingAs(User::factory()->create());

        $monitoredUser = MonitoredUser::factory()->create([
            'name' => 'VLAN40 - Peleyo',
            'queue_name' => 'VLAN40 - Peleyo',
        ]);

        MonthlyUserSummary::factory()->create([
            'monitored_user_id' => $monitoredUser->id,
            'billing_cycle_id' => $cycle->id,
            'total_bytes' => 999,
        ]);

        $response = $this->get('/api/dashboard/export/users.csv?range=cycle');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertSee('VLAN40 - Peleyo');
    }
}
