<?php

namespace App\Services\TrafficAnalytics\Sources;

use App\Services\TrafficAnalytics\Contracts\AnalyticsSourceInterface;

class FlowAnalyticsSource implements AnalyticsSourceInterface
{
    public function sourceType(): string
    {
        return 'flow';
    }

    public function normalize(mixed $payload): array
    {
        $items = is_array($payload) ? ($payload['items'] ?? $payload) : [];

        return collect($items)->map(fn (array $item): array => [
            'observed_name' => $item['label'] ?? $item['observed_name'] ?? null,
            'destination_host' => $item['destination_host'] ?? $item['sni'] ?? $item['dns_name'] ?? null,
            'destination_ip' => $item['destination_ip'] ?? null,
            'category_name' => $item['category_name'] ?? null,
            'app_name' => $item['app_name'] ?? null,
            'upload_bytes' => (int) ($item['upload_bytes'] ?? 0),
            'download_bytes' => (int) ($item['download_bytes'] ?? 0),
            'total_bytes' => (int) ($item['total_bytes'] ?? (($item['upload_bytes'] ?? 0) + ($item['download_bytes'] ?? 0))),
            'protocol' => $item['protocol'] ?? null,
            'confidence_score' => isset($item['confidence_score']) ? (float) $item['confidence_score'] : null,
            'recorded_at' => $item['recorded_at'] ?? now()->toIso8601String(),
            'monitored_user' => $item['monitored_user'] ?? $item['user'] ?? null,
            'isp' => $item['isp'] ?? null,
        ])->all();
    }
}
