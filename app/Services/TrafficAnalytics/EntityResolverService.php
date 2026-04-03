<?php

namespace App\Services\TrafficAnalytics;

use App\Models\TrafficEntity;
use App\Models\TrafficEntityAlias;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class EntityResolverService
{
    public function __construct(
        private readonly CategoryResolverService $categoryResolver,
    ) {
    }

    /**
     * @param  array<string, mixed>  $observation
     * @return array{entity: TrafficEntity, confidence: float, match_type: string}
     */
    public function resolve(array $observation): array
    {
        $candidates = array_values(array_filter([
            $observation['destination_host'] ?? null,
            $observation['app_name'] ?? null,
            $observation['observed_name'] ?? null,
            $observation['category_name'] ?? null,
        ]));

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeValue($candidate);

            $aliasMatch = TrafficEntityAlias::query()
                ->where('alias_name', $normalized)
                ->with('entity')
                ->first();

            if ($aliasMatch?->entity) {
                return [
                    'entity' => $aliasMatch->entity,
                    'confidence' => 0.99,
                    'match_type' => 'exact_alias',
                ];
            }
        }

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeValue($candidate);
            $mapping = $this->mappingMatch($normalized);

            if ($mapping) {
                return [
                    'entity' => $this->persistMappedEntity($mapping),
                    'confidence' => (float) ($mapping['confidence'] ?? 0.75),
                    'match_type' => 'mapped_pattern',
                ];
            }
        }

        $category = $this->categoryResolver->resolve(
            Arr::get($observation, 'category_name'),
            Arr::get($observation, 'observed_name') ?? Arr::get($observation, 'app_name'),
        );

        if ($category !== 'Unknown Encrypted') {
            return [
                'entity' => $this->findOrCreateCategoryEntity($category),
                'confidence' => 0.55,
                'match_type' => 'category_fallback',
            ];
        }

        return [
            'entity' => $this->unknownEntity(),
            'confidence' => 0.15,
            'match_type' => 'unknown',
        ];
    }

    private function mappingMatch(string $candidate): ?array
    {
        foreach (config('traffic_analytics.mappings', []) as $mapping) {
            foreach (($mapping['patterns'] ?? []) as $pattern) {
                $pattern = strtolower((string) $pattern);

                if ($candidate === $pattern || Str::endsWith($candidate, '.'.$pattern) || str_contains($candidate, $pattern)) {
                    return $mapping;
                }
            }
        }

        return null;
    }

    private function persistMappedEntity(array $mapping): TrafficEntity
    {
        $entity = TrafficEntity::query()->firstOrCreate(
            ['canonical_name' => $mapping['canonical_name']],
            [
                'entity_type' => $mapping['entity_type'],
                'display_name' => $mapping['display_name'],
                'category_name' => $mapping['category_name'] ?? null,
                'vendor_name' => $mapping['vendor_name'] ?? null,
                'domain' => $mapping['domain'] ?? null,
                'app_signature' => $mapping['app_signature'] ?? null,
                'metadata' => $mapping['metadata'] ?? null,
            ],
        );

        foreach (($mapping['aliases'] ?? []) as $alias) {
            $entity->aliases()->firstOrCreate([
                'alias_name' => $this->normalizeValue($alias['name']),
                'alias_type' => $alias['type'],
            ]);
        }

        return $entity;
    }

    private function findOrCreateCategoryEntity(string $category): TrafficEntity
    {
        $canonical = Str::slug($category);

        return TrafficEntity::query()->firstOrCreate(
            ['canonical_name' => $canonical],
            [
                'entity_type' => 'category',
                'display_name' => $category,
                'category_name' => $category,
                'metadata' => [
                    'resolution' => 'category_only',
                    'confidence_label' => 'low',
                ],
            ],
        );
    }

    private function unknownEntity(): TrafficEntity
    {
        $unknown = config('traffic_analytics.unknown_entity');

        return TrafficEntity::query()->firstOrCreate(
            ['canonical_name' => $unknown['canonical_name']],
            Arr::only($unknown, ['entity_type', 'display_name', 'category_name', 'metadata']),
        );
    }

    private function normalizeValue(string $value): string
    {
        return Str::of($value)->trim()->lower()->replace(['https://', 'http://'], '')->trim('/')->toString();
    }
}
