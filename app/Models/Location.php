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
    ];

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }
}
