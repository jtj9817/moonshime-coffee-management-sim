<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LostSale extends Model
{
    /** @use HasFactory<\Database\Factories\LostSaleFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'location_id',
        'product_id',
        'day',
        'quantity_lost',
        'potential_revenue_lost',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'day' => 'integer',
        'quantity_lost' => 'integer',
        'potential_revenue_lost' => 'integer',
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
