<?php

namespace App\Http\Resources;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonitoredUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $summary = $this->relationLoaded('monthlySummaries') ? $this->monthlySummaries->first() : null;
        $lastSnapshotAt = $summary?->last_snapshot_at ?? $this->last_snapshot_at;

        if ($lastSnapshotAt !== null && ! $lastSnapshotAt instanceof CarbonInterface) {
            $lastSnapshotAt = now()->parse($lastSnapshotAt);
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'queue_name' => $this->queue_name,
            'subnet' => $this->subnet,
            'group_name' => $this->group_name,
            'total_bytes' => (int) ($summary?->total_bytes ?? $this->total_bytes ?? 0),
            'upload_bytes' => (int) ($summary?->upload_bytes ?? $this->upload_bytes ?? 0),
            'download_bytes' => (int) ($summary?->download_bytes ?? $this->download_bytes ?? 0),
            'quota_bytes' => (int) ($summary?->quota_bytes ?? $this->quota_bytes ?? $this->monthly_quota_bytes),
            'remaining_bytes' => (int) ($summary?->remaining_bytes ?? $this->remaining_bytes ?? $this->monthly_quota_bytes),
            'usage_percent' => (float) ($summary?->usage_percent ?? $this->usage_percent ?? 0),
            'state' => $summary?->state ?? $this->state ?? 'NORMAL',
            'current_max_limit' => $summary?->current_max_limit ?? $this->current_max_limit,
            'last_snapshot_at' => $lastSnapshotAt?->toIso8601String(),
        ];
    }
}
