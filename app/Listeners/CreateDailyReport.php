<?php

namespace App\Listeners;

use App\Events\TimeAdvanced;
use App\Models\Alert;
use App\Models\DailyReport;
use App\Models\Order;
use App\Models\SpikeEvent;
use App\Models\Transfer;

class CreateDailyReport
{
    /**
     * Handle the TimeAdvanced event.
     *
     * Generates a daily report for the PREVIOUS day (day - 1) when time advances.
     */
    public function handle(TimeAdvanced $event): void
    {
        $gameState = $event->gameState;
        $user = $gameState->user;
        $previousDay = $event->day - 1;

        // Skip day 0 (no prior day to report on)
        if ($previousDay < 1) {
            return;
        }

        // Don't create a duplicate report
        if (DailyReport::where('user_id', $user->id)->where('day', $previousDay)->exists()) {
            return;
        }

        $summary = [
            'orders_placed' => Order::where('user_id', $user->id)
                ->where('created_day', $previousDay)
                ->count(),
            'spikes_started' => SpikeEvent::where('user_id', $user->id)
                ->where('starts_at_day', $previousDay)
                ->count(),
            'spikes_ended' => SpikeEvent::where('user_id', $user->id)
                ->where('ends_at_day', $previousDay)
                ->count(),
            'alerts_generated' => Alert::where('user_id', $user->id)
                ->where('created_day', $previousDay)
                ->count(),
            'transfers_completed' => Transfer::where('user_id', $user->id)
                ->where('delivery_day', $previousDay)
                ->count(),
        ];

        DailyReport::create([
            'user_id' => $user->id,
            'day' => $previousDay,
            'summary_data' => $summary,
            'metrics' => [
                'cash' => $gameState->cash,
                'xp' => $gameState->xp,
            ],
        ]);
    }
}
