<?php

namespace App\Services\TrafficAnalytics\Sources;

use App\Services\TrafficAnalytics\Contracts\AnalyticsSourceInterface;

class ZeekAnalyticsSource implements AnalyticsSourceInterface
{
    public function sourceType(): string
    {
        return 'zeek';
    }

    public function normalize(mixed $payload): array
    {
        $items = is_array($payload) ? ($payload['items'] ?? $payload) : [];

        return collect($items)->map(fn (array $item): array => [
            'observed_name' => $item['service'] ?? $item['host'] ?? null,
            'destination_host' => $item['server_name'] ?? $item['dns_name'] ?? $item['host'] ?? null,
            'destination_ip' => $item['id.resp_h'] ?? $item['destination_ip'] ?? null,
            'category_name' => $item['category'] ?? null,
            'app_name' => $item['service'] ?? $item['app'] ?? null,
            'upload_bytes' => (int) ($item['orig_bytes'] ?? $item['upload_bytes'] ?? 0),
            'download_bytes' => (int) ($item['resp_bytes'] ?? $item['download_bytes'] ?? 0),
            'total_bytes' => (int) ($item['total_bytes'] ?? (($item['orig_bytes'] ?? 0) + ($item['resp_bytes'] ?? 0))),
            'protocol' => $item['proto'] ?? $item['protocol'] ?? null,
            'confidence_score' => isset($item['confidence']) ? (float) $item['confidence'] : null,
            'recorded_at' => $item['ts'] ?? $item['recorded_at'] ?? now()->toIso8601String(),
            'monitored_user' => $item['monitored_user'] ?? $item['user'] ?? null,
            'isp' => $item['isp'] ?? null,
        ])->all();
    }
}
