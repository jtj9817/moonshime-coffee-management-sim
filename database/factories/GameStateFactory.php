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
            'cash' => fake()->randomFloat(2, 1000, 20000),
            'xp' => 0,
            'day' => 1,
        ];
    }
}
