<?php

namespace App\QuestTriggers;

use App\Contracts\QuestTrigger;
use App\Models\SpikeEvent;
use App\Models\User;

class SpikesResolvedTrigger implements QuestTrigger
{
    public function currentValue(User $user, array $params = []): int
    {
        return SpikeEvent::forUser($user->id)
            ->resolvedByPlayer()
            ->count();
    }
}
