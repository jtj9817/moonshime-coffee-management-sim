<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Route>
 */
class RouteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_id' => Location::factory(),
            'target_id' => Location::factory(),
            'transport_mode' => $this->faker->randomElement(['Truck', 'Air', 'Ship']),
            'weights' => [
                'cost' => $this->faker->numberBetween(10, 100),
                'time' => $this->faker->numberBetween(1, 10),
            ],
            'is_active' => true,
        ];
    }
}