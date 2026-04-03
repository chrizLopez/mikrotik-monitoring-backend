<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Isp extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'interface_name',
        'display_order',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(IspSnapshot::class);
    }

    public function routeStatusSnapshots(): HasMany
    {
        return $this->hasMany(RouteStatusSnapshot::class);
    }

    public function healthSnapshots(): HasMany
    {
        return $this->hasMany(IspHealthSnapshot::class);
    }

    public function resolveRouteBinding($value, $field = null): ?EloquentModel
    {
        $query = $this->newQuery();

        if ($field !== null) {
            return $query->where($field, $value)->first();
        }

        $resolved = $query
            ->whereKey($value)
            ->orWhere('interface_name', $value)
            ->first();

        if (! $resolved) {
            throw (new ModelNotFoundException())->setModel(self::class, [$value]);
        }

        return $resolved;
    }
}
