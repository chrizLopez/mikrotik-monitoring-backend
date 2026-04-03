<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\Isp;
use App\Models\MonitoredUser;
use App\Models\TrafficEntity;
use App\Models\TrafficObservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrafficAnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_sites_and_apps_endpoints_return_ranked_entities(): void
    {
        $this->seedTrafficData();

        $this->getJson('/api/dashboard/traffic/top-sites?range=cycle&limit=10')
            ->assertOk()
            ->assertJsonPath('data.items.0.display_name', 'YouTube');

        $this->getJson('/api/dashboard/traffic/top-apps?range=cycle&limit=10')
            ->assertOk()
            ->assertJsonPath('data.items.0.display_name', 'Discord');
    }

    public function test_user_isp_group_and_category_endpoints_return_filtered_traffic(): void
    {
        [$user, $isp] = $this->seedTrafficData();

        $this->getJson("/api/dashboard/traffic/users/{$user->id}/top-destinations?range=cycle")
            ->assertOk()
            ->assertJsonPath('data.items.0.display_name', 'YouTube');

        $this->getJson("/api/dashboard/traffic/isps/{$isp->id}/top-destinations?range=cycle")
            ->assertOk()
            ->assertJsonPath('data.items.0.display_name', 'YouTube');

        $this->getJson('/api/dashboard/traffic/groups/top-destinations?group=A&range=cycle')
            ->assertOk()
            ->assertJsonPath('data.items.0.display_name', 'YouTube');

        $this->getJson('/api/dashboard/traffic/top-categories?range=cycle')
            ->assertOk()
            ->assertJsonPath('data.items.0.label', 'Streaming');
    }

    public function test_overview_history_and_export_expose_coverage_and_unknown_bytes(): void
    {
        [$user] = $this->seedTrafficData(includeUnknown: true);
        $youtube = TrafficEntity::query()->where('canonical_name', 'youtube')->firstOrFail();

        $this->getJson("/api/dashboard/traffic/history?entity_id={$youtube->id}&range=cycle")
            ->assertOk()
            ->assertJsonPath('data.entity.display_name', 'YouTube');

        $this->getJson('/api/dashboard/traffic/overview?range=cycle')
            ->assertOk()
            ->assertJsonPath('data.total_unclassified_bytes', 1000)
            ->assertJsonPath('data.top_sites.0.display_name', 'YouTube');

        $this->get('/api/dashboard/traffic/export.csv?range=cycle')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    private function seedTrafficData(bool $includeUnknown = false): array
    {
        $cycle = BillingCycle::factory()->create(['is_current' => true]);
        Sanctum::actingAs(User::factory()->create());
        $isp = Isp::factory()->create(['name' => 'Old Starlink', 'interface_name' => 'ether1']);
        $user = MonitoredUser::factory()->create(['name' => 'Home Router', 'queue_name' => 'Home Router', 'group_name' => 'Group A']);
        $site = TrafficEntity::query()->create([
            'entity_type' => 'website',
            'canonical_name' => 'youtube',
            'display_name' => 'YouTube',
            'category_name' => 'Streaming',
        ]);
        $app = TrafficEntity::query()->create([
            'entity_type' => 'app',
            'canonical_name' => 'discord',
            'display_name' => 'Discord',
            'category_name' => 'Communication',
        ]);

        TrafficObservation::query()->create([
            'source_type' => 'manual_import',
            'monitored_user_id' => $user->id,
            'isp_id' => $isp->id,
            'traffic_entity_id' => $site->id,
            'destination_host' => 'youtube.com',
            'upload_bytes' => 100,
            'download_bytes' => 900,
            'total_bytes' => 1000,
            'confidence_score' => 0.99,
            'recorded_at' => $cycle->starts_at->copy()->addHour(),
        ]);
        TrafficObservation::query()->create([
            'source_type' => 'manual_import',
            'monitored_user_id' => $user->id,
            'isp_id' => $isp->id,
            'traffic_entity_id' => $app->id,
            'app_name' => 'discord',
            'upload_bytes' => 400,
            'download_bytes' => 400,
            'total_bytes' => 800,
            'confidence_score' => 0.75,
            'recorded_at' => $cycle->starts_at->copy()->addHours(2),
        ]);

        if ($includeUnknown) {
            $unknown = TrafficEntity::query()->create([
                'entity_type' => 'unknown',
                'canonical_name' => 'unknown-encrypted',
                'display_name' => 'Unknown Encrypted',
                'category_name' => 'Unknown Encrypted',
            ]);
            TrafficObservation::query()->create([
                'source_type' => 'manual_import',
                'monitored_user_id' => $user->id,
                'isp_id' => $isp->id,
                'traffic_entity_id' => $unknown->id,
                'upload_bytes' => 500,
                'download_bytes' => 500,
                'total_bytes' => 1000,
                'confidence_score' => 0.1,
                'recorded_at' => $cycle->starts_at->copy()->addHours(3),
            ]);
        }

        return [$user, $isp];
    }
}
