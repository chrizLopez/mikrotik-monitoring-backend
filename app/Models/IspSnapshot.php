<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IspSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'isp_id',
        'rx_bps',
        'tx_bps',
        'rx_bytes_total',
        'tx_bytes_total',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function isp(): BelongsTo
    {
        return $this->belongsTo(Isp::class);
    }
}
