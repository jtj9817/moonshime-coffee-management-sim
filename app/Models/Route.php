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
        'weights',
        'is_active',
    ];

    protected $casts = [
        'weights' => 'array',
        'is_active' => 'boolean',
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