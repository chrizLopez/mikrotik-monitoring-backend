<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'monitored_user_id',
        'upload_bytes_total',
        'download_bytes_total',
        'total_bytes',
        'max_limit',
        'state',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function monitoredUser(): BelongsTo
    {
        return $this->belongsTo(MonitoredUser::class);
    }
}
