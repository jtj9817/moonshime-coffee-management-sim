<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quest extends Model
{
    /** @use HasFactory<\Database\Factories\QuestFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'type',
        'title',
        'description',
        'target_value',
        'reward_cash_cents',
        'reward_xp',
        'is_active',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'target_value' => 'integer',
        'reward_cash_cents' => 'integer',
        'reward_xp' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'meta' => 'array',
    ];

    public function userQuests(): HasMany
    {
        return $this->hasMany(UserQuest::class);
    }
}
