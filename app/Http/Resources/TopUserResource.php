<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (is_array($this->resource)) {
            return $this->resource;
        }

        if ($this->resource instanceof \App\Models\MonthlyUserSummary) {
            return [
                'id' => $this->monitoredUser->id,
                'name' => $this->monitoredUser->name,
                'queue_name' => $this->monitoredUser->queue_name,
                'group_name' => $this->monitoredUser->group_name,
                'total_bytes' => $this->total_bytes,
                'upload_bytes' => $this->upload_bytes,
                'download_bytes' => $this->download_bytes,
                'remaining_quota_bytes' => $this->remaining_bytes,
                'quota_bytes' => $this->quota_bytes,
                'usage_percent' => (float) $this->usage_percent,
                'state' => $this->state,
                'current_max_limit' => $this->current_max_limit,
            ];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'queue_name' => $this->queue_name,
            'group_name' => $this->group_name,
            'total_bytes' => (int) ($this->range_total_bytes ?? 0),
            'upload_bytes' => null,
            'download_bytes' => null,
            'remaining_quota_bytes' => null,
            'quota_bytes' => null,
            'usage_percent' => null,
            'state' => null,
            'current_max_limit' => null,
        ];
    }
}
