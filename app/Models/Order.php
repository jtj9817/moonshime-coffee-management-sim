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
        'route_id',
        'status',
        'total_cost',
        'delivery_date',
        'delivery_day',
    ];

    protected function casts(): array
    {
        return [
            'delivery_date' => 'datetime',
            'total_cost' => 'integer',
            'status' => OrderState::class,
            'delivery_day' => 'integer',
            'route_id' => 'integer',
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

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}