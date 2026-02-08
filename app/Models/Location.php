<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    /** @use HasFactory<\Database\Factories\LocationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'address',
        'max_storage',
        'sell_price',
        'type',
    ];

    protected $casts = [
        'max_storage' => 'integer',
        'sell_price' => 'integer',
    ];

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function outgoingRoutes(): HasMany
    {
        return $this->hasMany(Route::class, 'source_id');
    }

    public function incomingRoutes(): HasMany
    {
        return $this->hasMany(Route::class, 'target_id');
    }
}
