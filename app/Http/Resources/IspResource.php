<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IspResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latestSnapshot = $this->snapshots->first();
        $latestRouteStatus = $this->routeStatusSnapshots->first();
        $rangeUsage = $this->getAttribute('range_usage') ?? [];
        $totals = $rangeUsage['totals'] ?? [];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'interface_name' => $this->interface_name,
            'current_rx_bps' => $latestSnapshot?->rx_bps,
            'current_tx_bps' => $latestSnapshot?->tx_bps,
            'download_bytes' => (int) ($totals['rx_bytes_total'] ?? 0),
            'upload_bytes' => (int) ($totals['tx_bytes_total'] ?? 0),
            'total_bytes' => (int) ($rangeUsage['total_bytes'] ?? 0),
            'current_total_bytes' => (int) ($rangeUsage['total_bytes'] ?? 0),
            'status' => $latestRouteStatus?->status === 'online' ? 'online' : 'offline',
            'last_snapshot_at' => $latestSnapshot?->recorded_at?->toIso8601String(),
        ];
    }
}
