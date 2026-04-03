<?php

namespace Tests\Unit;

use App\Models\TrafficEntity;
use App\Models\TrafficEntityAlias;
use App\Services\TrafficAnalytics\CategoryResolverService;
use App\Services\TrafficAnalytics\EntityResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_prefers_exact_alias_match(): void
    {
        $entity = TrafficEntity::query()->create([
            'entity_type' => 'website',
            'canonical_name' => 'youtube',
            'display_name' => 'YouTube',
            'category_name' => 'Streaming',
        ]);
        TrafficEntityAlias::query()->create([
            'traffic_entity_id' => $entity->id,
            'alias_name' => 'youtube.com',
            'alias_type' => 'domain',
        ]);

        $resolver = new EntityResolverService(new CategoryResolverService());
        $resolved = $resolver->resolve(['destination_host' => 'youtube.com']);

        $this->assertSame('YouTube', $resolved['entity']->display_name);
        $this->assertSame('exact_alias', $resolved['match_type']);
    }

    public function test_resolver_uses_configured_mapping_and_category_fallback(): void
    {
        $resolver = new EntityResolverService(new CategoryResolverService());

        $mapped = $resolver->resolve(['destination_host' => 'r3---sn.googlevideo.com']);
        $fallback = $resolver->resolve(['observed_name' => 'generic social feed', 'category_name' => 'social']);

        $this->assertSame('YouTube', $mapped['entity']->display_name);
        $this->assertSame('Streaming', $mapped['entity']->category_name);
        $this->assertSame('Social Media', $fallback['entity']->display_name);
        $this->assertSame('category_fallback', $fallback['match_type']);
    }

    public function test_resolver_falls_back_to_unknown_bucket(): void
    {
        $resolver = new EntityResolverService(new CategoryResolverService());
        $resolved = $resolver->resolve(['observed_name' => null]);

        $this->assertSame('Unknown Encrypted', $resolved['entity']->display_name);
        $this->assertSame('unknown', $resolved['match_type']);
    }
}
