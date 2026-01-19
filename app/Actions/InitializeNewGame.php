<?php

namespace App\Actions;

use App\Models\GameState;
use App\Models\User;
use Database\Seeders\SpikeSeeder;

/**
 * Reusable action for initializing spikes when a real user starts a new game.
 */
class InitializeNewGame
{
    /**
     * Initialize a new game for a user, including seeding initial spikes.
     */
    public function handle(User $user): GameState
    {
        $gameState = GameState::firstOrCreate(
            ['user_id' => $user->id],
            ['cash' => 1000000, 'xp' => 0, 'day' => 1]
        );

        // Only seed spikes if this is a fresh game (day 1)
        if ($gameState->wasRecentlyCreated || $gameState->day === 1) {
            app(SpikeSeeder::class)->seedInitialSpikes($gameState);
        }

        return $gameState;
    }
}
