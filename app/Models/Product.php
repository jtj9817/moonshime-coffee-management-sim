<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'category',
        'is_perishable',
        'storage_cost',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'is_perishable' => 'boolean',
            'storage_cost' => 'float',
            'unit_price' => 'float',
        ];
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(Vendor::class);
    }
}
