<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpikeEvent extends Model
{
    /** @use HasFactory<\Database\Factories\SpikeEventFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'magnitude',
        'duration',
        'location_id',
        'product_id',
        'affected_route_id',
        'parent_id',
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
        'user_id' => 'integer',
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

    public function affectedRoute(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'affected_route_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(SpikeEvent::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(SpikeEvent::class, 'parent_id');
    }
}
