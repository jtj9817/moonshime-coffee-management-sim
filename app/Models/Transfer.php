<?php

namespace App\Models;

use App\States\TransferState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\ModelStates\HasStates;

class Transfer extends Model
{
    /** @use HasFactory<\Database\Factories\TransferFactory> */
    use HasFactory, HasStates, HasUuids;

    protected $fillable = [
        'user_id',
        'source_location_id',
        'target_location_id',
        'product_id',
        'quantity',
        'status',
        'delivery_day',
    ];

    protected function casts(): array
    {
        return [
            'status' => TransferState::class,
            'user_id' => 'integer',
            'quantity' => 'integer',
            'delivery_day' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'source_location_id');
    }

    public function targetLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'target_location_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
