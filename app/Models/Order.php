<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Relations\HasMany;

use App\States\OrderState;
use Spatie\ModelStates\HasStates;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory, HasUuids, HasStates;

    protected $fillable = [
        'user_id',
        'vendor_id',
        'location_id',
        'status',
        'total_cost',
        'total_transit_days',
        'delivery_date',
        'delivery_day',
        'created_day',
    ];

    protected function casts(): array
    {
        return [
            'delivery_date' => 'datetime',
            'total_cost' => 'float',
            'total_transit_days' => 'integer',
            'status' => OrderState::class,
            'delivery_day' => 'integer',
            'created_day' => 'integer',
            'user_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
