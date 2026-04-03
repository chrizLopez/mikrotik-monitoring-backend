<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrafficEntityDailySummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'traffic_entity_id',
        'monitored_user_id',
        'isp_id',
        'date',
        'upload_bytes',
        'download_bytes',
        'total_bytes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'upload_bytes' => 'integer',
            'download_bytes' => 'integer',
            'total_bytes' => 'integer',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(TrafficEntity::class, 'traffic_entity_id');
    }
}
