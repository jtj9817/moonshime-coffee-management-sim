<?php

namespace App\QuestTriggers;

use App\Contracts\QuestTrigger;
use App\Models\Order;
use App\Models\User;

class OrdersPlacedTrigger implements QuestTrigger
{
    public function currentValue(User $user, array $params = []): int
    {
        return Order::where('user_id', $user->id)->count();
    }
}
