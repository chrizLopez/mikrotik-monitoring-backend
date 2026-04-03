<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'starts_at',
        'ends_at',
        'label',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_current' => 'boolean',
        ];
    }

    public function monthlyUserSummaries(): HasMany
    {
        return $this->hasMany(MonthlyUserSummary::class);
    }
}
