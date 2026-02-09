<?php

namespace Database\Factories;

use App\Models\Quest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quest>
 */
class QuestFactory extends Factory
{
    protected $model = Quest::class;

    public function definition(): array
    {
        return [
            'type' => 'orders_placed',
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(8),
            'target_value' => fake()->numberBetween(1, 10),
            'reward_cash_cents' => fake()->numberBetween(10000, 100000),
            'reward_xp' => fake()->numberBetween(50, 500),
            'is_active' => true,
            'sort_order' => 0,
            'trigger_class' => null,
            'trigger_params' => null,
        ];
    }
}
