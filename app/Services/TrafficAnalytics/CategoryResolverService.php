<?php

namespace App\Services\TrafficAnalytics;

class CategoryResolverService
{
    public function resolve(?string $rawCategory, ?string $rawName = null): string
    {
        $candidates = array_filter([$rawCategory, $rawName]);
        $fallbacks = config('traffic_analytics.category_fallbacks', []);

        foreach ($candidates as $candidate) {
            $normalized = strtolower(trim((string) $candidate));

            foreach ($fallbacks as $needle => $category) {
                if ($normalized === $needle || str_contains($normalized, $needle)) {
                    return $category;
                }
            }
        }

        return 'Unknown Encrypted';
    }
}
