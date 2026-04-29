<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupUsageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'group_key' => $this['group_key'] ?? null,
            'group_name' => $this['group_name'],
            'policy' => $this['policy'] ?? [],
            'subnets' => $this['subnets'] ?? [],
            'total_bytes' => $this['total_bytes'],
            'user_count' => $this['user_count'] ?? 0,
        ];
    }
}
