<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteStatusSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'isp_id',
        'status',
        'details',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function isp(): BelongsTo
    {
        return $this->belongsTo(Isp::class);
    }
}
