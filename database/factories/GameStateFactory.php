<?php

namespace Database\Factories;

use App\Models\GameState;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GameState>
 */
class GameStateFactory extends Factory
{
    protected $model = GameState::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'cash' => fake()->numberBetween(100000, 2000000), // cents
            'xp' => 0,
            'day' => 1,
        ];
    }
}
