<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrafficEntity extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'canonical_name',
        'display_name',
        'category_name',
        'vendor_name',
        'domain',
        'app_signature',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(TrafficEntityAlias::class);
    }

    public function observations(): HasMany
    {
        return $this->hasMany(TrafficObservation::class);
    }
}
