<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrafficObservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_type',
        'monitored_user_id',
        'isp_id',
        'traffic_entity_id',
        'observed_name',
        'destination_host',
        'destination_ip',
        'category_name',
        'app_name',
        'upload_bytes',
        'download_bytes',
        'total_bytes',
        'protocol',
        'confidence_score',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'upload_bytes' => 'integer',
            'download_bytes' => 'integer',
            'total_bytes' => 'integer',
            'confidence_score' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(TrafficEntity::class, 'traffic_entity_id');
    }

    public function monitoredUser(): BelongsTo
    {
        return $this->belongsTo(MonitoredUser::class);
    }

    public function isp(): BelongsTo
    {
        return $this->belongsTo(Isp::class);
    }
}
