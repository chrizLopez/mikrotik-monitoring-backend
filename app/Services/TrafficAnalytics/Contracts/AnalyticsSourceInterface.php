<?php

namespace App\Services\TrafficAnalytics\Contracts;

interface AnalyticsSourceInterface
{
    public function sourceType(): string;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function normalize(mixed $payload): array;
}
