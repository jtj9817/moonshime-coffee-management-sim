<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameState extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cash',
        'xp',
        'day',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}