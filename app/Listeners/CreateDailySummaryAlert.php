<?php

namespace App\Listeners;

use App\Events\TimeAdvanced;
use App\Models\Alert;
use App\Models\DemandEvent;
use App\Models\LostSale;
use Illuminate\Support\Facades\DB;

class CreateDailySummaryAlert
{
    /**
     * Create a daily summary alert aggregating the day's activity.
     */
    public function handle(TimeAdvanced $event): void
    {
        $gameState = $event->gameState;
        $userId = $gameState->user_id;
        $day = $event->day;

        // Prevent duplicate summaries for the same day
        $exists = Alert::where('user_id', $userId)
            ->where('type', 'summary')
            ->where('created_day', $day)
            ->exists();

        if ($exists) {
            return;
        }

        // Aggregate demand events
        $demandSummary = DemandEvent::where('user_id', $userId)
            ->where('day', $day)
            ->selectRaw('COALESCE(SUM(fulfilled_quantity), 0) as units_sold, COALESCE(SUM(revenue), 0) as revenue')
            ->first();

        $unitsSold = (int) ($demandSummary->units_sold ?? 0);
        $revenue = (int) ($demandSummary->revenue ?? 0);

        // Aggregate lost sales
        $lostSalesTotal = (int) LostSale::where('user_id', $userId)
            ->where('day', $day)
            ->sum('quantity_lost');

        // Calculate storage fees (same formula as ApplyStorageCosts)
        $storageFees = (int) DB::table('inventories')
            ->join('products', 'inventories.product_id', '=', 'products.id')
            ->where('inventories.user_id', $userId)
            ->sum(DB::raw('inventories.quantity * products.storage_cost'));

        // Build summary message
        $parts = [];
        if ($unitsSold > 0) {
            $parts[] = "{$unitsSold} units sold";
        }
        if ($lostSalesTotal > 0) {
            $parts[] = "{$lostSalesTotal} units lost to stockouts";
        }
        if ($storageFees > 0) {
            $formattedFees = number_format($storageFees / 100, 2);
            $parts[] = "\${$formattedFees} storage fees";
        }

        $message = "Day {$day} Summary: " . (count($parts) > 0 ? implode(', ', $parts) . '.' : 'No activity.');

        Alert::create([
            'user_id' => $userId,
            'type' => 'summary',
            'severity' => 'info',
            'message' => $message,
            'created_day' => $day,
            'data' => [
                'units_sold' => $unitsSold,
                'lost_sales' => $lostSalesTotal,
                'storage_fees' => $storageFees,
                'revenue' => $revenue,
            ],
        ]);
    }
}
