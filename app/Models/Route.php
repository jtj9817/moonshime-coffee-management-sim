<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Route extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_id',
        'target_id',
        'transport_mode',
        'cost',
        'transit_days',
        'capacity',
        'is_active',
        'weather_vulnerability',
    ];

    protected $casts = [
        'cost' => 'integer',
        'transit_days' => 'integer',
        'capacity' => 'integer',
        'is_active' => 'boolean',
        'weather_vulnerability' => 'boolean',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'source_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'target_id');
    }
}
