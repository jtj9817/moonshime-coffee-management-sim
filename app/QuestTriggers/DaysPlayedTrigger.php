<?php

namespace App\QuestTriggers;

use App\Contracts\QuestTrigger;
use App\Models\GameState;
use App\Models\User;

class DaysPlayedTrigger implements QuestTrigger
{
    public function currentValue(User $user, array $params = []): int
    {
        $gameState = GameState::where('user_id', $user->id)->first();

        return $gameState ? $gameState->day : 0;
    }
}
