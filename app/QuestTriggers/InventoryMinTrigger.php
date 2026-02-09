<?php

namespace App\QuestTriggers;

use App\Contracts\QuestTrigger;
use App\Models\Inventory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InventoryMinTrigger implements QuestTrigger
{
    public function currentValue(User $user, array $params = []): int
    {
        $totals = Inventory::where('user_id', $user->id)
            ->select('product_id', DB::raw('SUM(quantity) as total_quantity'))
            ->groupBy('product_id')
            ->pluck('total_quantity');

        if ($totals->isEmpty()) {
            return 0;
        }

        return (int) $totals->min();
    }
}
