<?php

namespace App\Services;

use App\Models\GameState;
use App\Events\TimeAdvanced;

class SimulationService
{
    public function __construct(
        protected GameState $gameState
    ) {}

    /**
     * Advance the game simulation by one day.
     */
    public function advanceTime(): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () {
            $this->gameState->increment('day');
            
            event(new TimeAdvanced($this->gameState->day));
        });
    }

    /**
     * Get the current game day.
     */
    public function getCurrentDay(): int
    {
        return $this->gameState->day;
    }
}
