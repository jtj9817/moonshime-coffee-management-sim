<?php

namespace Database\Seeders;

use App\Models\Quest;
use App\QuestTriggers\DaysPlayedTrigger;
use App\QuestTriggers\InventoryMinTrigger;
use App\QuestTriggers\OrdersPlacedTrigger;
use App\QuestTriggers\SpikesResolvedTrigger;
use App\QuestTriggers\TransfersCompletedTrigger;
use Illuminate\Database\Seeder;

class QuestSeeder extends Seeder
{
    public function run(): void
    {
        $quests = [
            [
                'type' => 'orders_placed',
                'title' => 'First Procurement',
                'description' => 'Place your first order with a supplier.',
                'target_value' => 1,
                'reward_cash_cents' => 25000,
                'reward_xp' => 50,
                'sort_order' => 1,
                'trigger_class' => OrdersPlacedTrigger::class,
            ],
            [
                'type' => 'orders_placed',
                'title' => 'Bulk Buyer',
                'description' => 'Place 5 orders to establish supplier relationships.',
                'target_value' => 5,
                'reward_cash_cents' => 75000,
                'reward_xp' => 150,
                'sort_order' => 2,
                'trigger_class' => OrdersPlacedTrigger::class,
            ],
            [
                'type' => 'days_played',
                'title' => 'Survivor',
                'description' => 'Survive your first week of operations.',
                'target_value' => 7,
                'reward_cash_cents' => 50000,
                'reward_xp' => 100,
                'sort_order' => 3,
                'trigger_class' => DaysPlayedTrigger::class,
            ],
            [
                'type' => 'days_played',
                'title' => 'Veteran Operator',
                'description' => 'Manage operations for 30 days.',
                'target_value' => 30,
                'reward_cash_cents' => 200000,
                'reward_xp' => 500,
                'sort_order' => 4,
                'trigger_class' => DaysPlayedTrigger::class,
            ],
            [
                'type' => 'inventory',
                'title' => 'Stock Champion',
                'description' => 'Maintain at least 100 units of each product.',
                'target_value' => 100,
                'reward_cash_cents' => 50000,
                'reward_xp' => 100,
                'sort_order' => 5,
                'trigger_class' => InventoryMinTrigger::class,
            ],
            [
                'type' => 'transfers_completed',
                'title' => 'Logistics Rookie',
                'description' => 'Complete your first inter-location transfer.',
                'target_value' => 1,
                'reward_cash_cents' => 30000,
                'reward_xp' => 75,
                'sort_order' => 6,
                'trigger_class' => TransfersCompletedTrigger::class,
            ],
            [
                'type' => 'spikes_resolved',
                'title' => 'Crisis Manager',
                'description' => 'Resolve a spike event through direct action.',
                'target_value' => 1,
                'reward_cash_cents' => 100000,
                'reward_xp' => 200,
                'sort_order' => 7,
                'trigger_class' => SpikesResolvedTrigger::class,
            ],
        ];

        foreach ($quests as $quest) {
            Quest::updateOrCreate(
                ['type' => $quest['type'], 'title' => $quest['title']],
                array_merge($quest, ['is_active' => true])
            );
        }
    }
}
