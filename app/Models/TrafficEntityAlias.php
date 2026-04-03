<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrafficEntityAlias extends Model
{
    use HasFactory;

    protected $fillable = [
        'traffic_entity_id',
        'alias_name',
        'alias_type',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(TrafficEntity::class, 'traffic_entity_id');
    }
}
