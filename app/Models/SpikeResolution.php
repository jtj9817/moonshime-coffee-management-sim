<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpikeResolution extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'spike_event_id',
        'action_type',
        'action_detail',
        'cost_cents',
        'effect',
        'game_day',
    ];

    protected $casts = [
        'cost_cents' => 'integer',
        'effect' => 'array',
        'game_day' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function spikeEvent(): BelongsTo
    {
        return $this->belongsTo(SpikeEvent::class);
    }
}
