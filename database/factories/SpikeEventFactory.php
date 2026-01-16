<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SpikeEvent>
 */
class SpikeEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement(['demand', 'delay', 'price', 'breakdown']),
            'magnitude' => $this->faker->randomFloat(2, 0.1, 2.0),
            'duration' => $this->faker->numberBetween(1, 5),
            'location_id' => Location::factory(),
            'product_id' => Product::factory(),
            'starts_at_day' => $this->faker->numberBetween(1, 100),
            'ends_at_day' => function (array $attributes) {
                return $attributes['starts_at_day'] + $attributes['duration'];
            },
            'is_active' => false,
            'meta' => null,
        ];
    }
}
