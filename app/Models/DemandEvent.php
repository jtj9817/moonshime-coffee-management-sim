<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandEvent extends Model
{
    /** @use HasFactory<\Database\Factories\DemandEventFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'day',
        'location_id',
        'product_id',
        'requested_quantity',
        'fulfilled_quantity',
        'lost_quantity',
        'unit_price',
        'revenue',
        'lost_revenue',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'day' => 'integer',
        'requested_quantity' => 'integer',
        'fulfilled_quantity' => 'integer',
        'lost_quantity' => 'integer',
        'unit_price' => 'integer',
        'revenue' => 'integer',
        'lost_revenue' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
