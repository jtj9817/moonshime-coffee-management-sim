<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationDailyMetric extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'location_id',
        'day',
        'revenue',
        'cogs',
        'opex',
        'net_profit',
        'units_sold',
        'stockouts',
        'satisfaction',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'day' => 'integer',
        'revenue' => 'integer',
        'cogs' => 'integer',
        'opex' => 'integer',
        'net_profit' => 'integer',
        'units_sold' => 'integer',
        'stockouts' => 'integer',
        'satisfaction' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
