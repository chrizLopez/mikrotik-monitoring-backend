<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DestinationSnapshot extends Model
{
    protected $fillable = [
        'category',
        'name',
        'visits',
        'total_bytes',
        'top_user',
        'last_seen_at',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'visits' => 'integer',
            'total_bytes' => 'integer',
            'last_seen_at' => 'datetime',
            'recorded_at' => 'datetime',
        ];
    }
}
