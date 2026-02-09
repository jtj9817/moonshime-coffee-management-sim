<?php

namespace App\QuestTriggers;

use App\Contracts\QuestTrigger;
use App\Models\LocationDailyMetric;
use App\Models\User;

class RevenueEarnedTrigger implements QuestTrigger
{
    public function currentValue(User $user, array $params = []): int
    {
        return (int) LocationDailyMetric::where('user_id', $user->id)
            ->sum('revenue');
    }
}
