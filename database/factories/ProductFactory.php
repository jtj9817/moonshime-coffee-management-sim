<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'category' => $this->faker->randomElement(['Beans', 'Milk', 'Syrup', 'Cups']),
            'is_perishable' => $this->faker->boolean(30),
            'storage_cost' => $this->faker->randomFloat(2, 0.1, 5.0),
        ];
    }
}