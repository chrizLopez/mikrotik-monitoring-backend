<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IspResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latestSnapshot = $this->relationLoaded('snapshots') ? $this->snapshots->first() : null;
        $latestRouteStatus = $this->relationLoaded('routeStatusSnapshots') ? $this->routeStatusSnapshots->first() : null;
        $rangeUsage = $this->getAttribute('range_usage') ?? [];
        $totals = $rangeUsage['totals'] ?? [];
        $wanMetadata = collect(config('dashboard.network_model.wans', []))
            ->firstWhere('interface_name', $this->interface_name);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'interface_name' => $this->interface_name,
            'gateway' => $wanMetadata['gateway'] ?? null,
            'connection_mark' => $wanMetadata['connection_mark'] ?? null,
            'routing_mark' => $wanMetadata['routing_mark'] ?? null,
            'share_percent_target' => $wanMetadata['share_percent'] ?? null,
            'architecture_role' => 'shared_pcc_member',
            'current_rx_bps' => $latestSnapshot?->rx_bps ?? $this->current_rx_bps,
            'current_tx_bps' => $latestSnapshot?->tx_bps ?? $this->current_tx_bps,
            'download_bytes' => (int) ($totals['rx_bytes_total'] ?? 0),
            'upload_bytes' => (int) ($totals['tx_bytes_total'] ?? 0),
            'total_bytes' => (int) ($rangeUsage['total_bytes'] ?? 0),
            'current_total_bytes' => (int) ($rangeUsage['total_bytes'] ?? 0),
            'status' => ($latestRouteStatus?->status ?? $this->status) === 'online' ? 'online' : 'offline',
            'last_snapshot_at' => ($latestSnapshot?->recorded_at ?? $this->last_snapshot_at)?->toIso8601String(),
        ];
    }
}
