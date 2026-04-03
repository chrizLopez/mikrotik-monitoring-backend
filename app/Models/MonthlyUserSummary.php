<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyUserSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'monitored_user_id',
        'billing_cycle_id',
        'upload_bytes',
        'download_bytes',
        'total_bytes',
        'quota_bytes',
        'remaining_bytes',
        'usage_percent',
        'state',
        'current_max_limit',
        'last_snapshot_at',
    ];

    protected function casts(): array
    {
        return [
            'usage_percent' => 'decimal:2',
            'last_snapshot_at' => 'datetime',
        ];
    }

    public function monitoredUser(): BelongsTo
    {
        return $this->belongsTo(MonitoredUser::class);
    }

    public function billingCycle(): BelongsTo
    {
        return $this->belongsTo(BillingCycle::class);
    }
}
