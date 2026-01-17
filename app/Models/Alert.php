<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    /** @use HasFactory<\Database\Factories\AlertFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'type',
        'severity',
        'location_id',
        'product_id',
        'spike_event_id',
        'message',
        'data',
        'is_read',
        'is_resolved',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'is_resolved' => 'boolean',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function spikeEvent(): BelongsTo
    {
        return $this->belongsTo(SpikeEvent::class);
    }
}
