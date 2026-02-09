<?php

namespace App\QuestTriggers;

use App\Contracts\QuestTrigger;
use App\Models\Transfer;
use App\Models\User;

class TransfersCompletedTrigger implements QuestTrigger
{
    public function currentValue(User $user, array $params = []): int
    {
        return Transfer::where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();
    }
}
