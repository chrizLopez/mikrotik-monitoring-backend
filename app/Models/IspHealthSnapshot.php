<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IspHealthSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'isp_id',
        'ping_target',
        'latency_ms',
        'packet_loss_percent',
        'jitter_ms',
        'status',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'latency_ms' => 'float',
            'packet_loss_percent' => 'float',
            'jitter_ms' => 'float',
            'recorded_at' => 'datetime',
        ];
    }

    public function isp(): BelongsTo
    {
        return $this->belongsTo(Isp::class);
    }
}
