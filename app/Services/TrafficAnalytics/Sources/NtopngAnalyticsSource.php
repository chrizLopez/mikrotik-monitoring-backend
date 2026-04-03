<?php

namespace App\Services\TrafficAnalytics\Sources;

use App\Services\TrafficAnalytics\Contracts\AnalyticsSourceInterface;

class NtopngAnalyticsSource implements AnalyticsSourceInterface
{
    public function sourceType(): string
    {
        return 'ntopng';
    }

    public function normalize(mixed $payload): array
    {
        $items = is_array($payload) ? ($payload['items'] ?? $payload) : [];

        return collect($items)->map(fn (array $item): array => [
            'observed_name' => $item['label'] ?? $item['name'] ?? null,
            'destination_host' => $item['host'] ?? $item['dns_name'] ?? $item['server_name'] ?? null,
            'destination_ip' => $item['ip'] ?? $item['server_ip'] ?? null,
            'category_name' => $item['category'] ?? null,
            'app_name' => $item['app'] ?? $item['application'] ?? null,
            'upload_bytes' => (int) ($item['bytes_sent'] ?? $item['upload_bytes'] ?? 0),
            'download_bytes' => (int) ($item['bytes_rcvd'] ?? $item['download_bytes'] ?? 0),
            'total_bytes' => (int) ($item['total_bytes'] ?? (($item['bytes_sent'] ?? 0) + ($item['bytes_rcvd'] ?? 0))),
            'protocol' => $item['protocol'] ?? null,
            'confidence_score' => isset($item['confidence']) ? (float) $item['confidence'] : null,
            'recorded_at' => $item['recorded_at'] ?? $item['timestamp'] ?? now()->toIso8601String(),
            'monitored_user' => $item['monitored_user'] ?? $item['user'] ?? null,
            'isp' => $item['isp'] ?? null,
        ])->all();
    }
}
