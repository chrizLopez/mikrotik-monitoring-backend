<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonitoredUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $summary = $this->monthlySummaries->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'queue_name' => $this->queue_name,
            'subnet' => $this->subnet,
            'group_name' => $this->group_name,
            'total_bytes' => $summary?->total_bytes ?? 0,
            'upload_bytes' => $summary?->upload_bytes ?? 0,
            'download_bytes' => $summary?->download_bytes ?? 0,
            'quota_bytes' => $summary?->quota_bytes ?? $this->monthly_quota_bytes,
            'remaining_bytes' => $summary?->remaining_bytes ?? $this->monthly_quota_bytes,
            'usage_percent' => (float) ($summary?->usage_percent ?? 0),
            'state' => $summary?->state ?? 'NORMAL',
            'current_max_limit' => $summary?->current_max_limit,
            'last_snapshot_at' => $summary?->last_snapshot_at?->toIso8601String(),
        ];
    }
}
