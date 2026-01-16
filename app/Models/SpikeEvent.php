<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpikeEvent extends Model
{
    /** @use HasFactory<\Database\Factories\SpikeEventFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'type',
        'magnitude',
        'duration',
        'location_id',
        'product_id',
        'starts_at_day',
        'ends_at_day',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'magnitude' => 'decimal:2',
        'duration' => 'integer',
        'starts_at_day' => 'integer',
        'ends_at_day' => 'integer',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}