<?php

namespace App\Http\Resources;

use App\Services\Support\RangePreset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var RangePreset $range */
        $range = $this['range'];

        return [
            'range' => [
                'key' => $range->key,
                'label' => $range->label,
                'start' => $range->start->toIso8601String(),
                'end' => $range->end->toIso8601String(),
                'bucket' => $range->bucket,
            ],
            'totals' => $this['totals'] ?? null,
            'points' => $this['points'],
        ];
    }
}
