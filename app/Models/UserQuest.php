<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserQuest extends Model
{
    /** @use HasFactory<\Database\Factories\UserQuestFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'quest_id',
        'current_value',
        'is_completed',
        'completed_at',
        'created_day',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'current_value' => 'integer',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'created_day' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }
}
