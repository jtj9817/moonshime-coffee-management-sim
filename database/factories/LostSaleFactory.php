<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LostSale>
 */
class LostSaleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'location_id' => Location::factory(),
            'product_id' => Product::factory(),
            'day' => $this->faker->numberBetween(1, 30),
            'quantity_lost' => $this->faker->numberBetween(1, 20),
            'potential_revenue_lost' => $this->faker->numberBetween(100, 10000), // cents
        ];
    }
}
