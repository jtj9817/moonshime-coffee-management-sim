<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Product;
use App\Models\Transfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transfer>
 */
class TransferFactory extends Factory
{
    protected $model = Transfer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_location_id' => Location::factory(),
            'target_location_id' => Location::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(10, 100),
            'status' => 'draft',
        ];
    }
}
