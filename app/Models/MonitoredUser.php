<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitoredUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'queue_name',
        'subnet',
        'group_name',
        'monthly_quota_bytes',
        'normal_max_limit',
        'throttled_max_limit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'monthly_quota_bytes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(UserSnapshot::class);
    }

    public function monthlySummaries(): HasMany
    {
        return $this->hasMany(MonthlyUserSummary::class);
    }

    public function trafficObservations(): HasMany
    {
        return $this->hasMany(TrafficObservation::class);
    }
}
