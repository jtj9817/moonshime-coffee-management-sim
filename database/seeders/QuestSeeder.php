<?php

namespace Database\Seeders;

use App\Models\Quest;
use Illuminate\Database\Seeder;

class QuestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Quest::updateOrCreate(
            [
                'type' => 'inventory',
                'title' => 'Stock Champion',
            ],
            [
                'description' => 'Maintain at least 100 units of each product',
                'target_value' => 100,
                'reward_cash_cents' => 500,
                'reward_xp' => 100,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
    }
}
