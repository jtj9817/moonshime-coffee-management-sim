<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Product;
use App\Models\Transfer;
use App\Models\User;
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
            'user_id' => User::factory(),
            'source_location_id' => Location::factory(),
            'target_location_id' => Location::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(10, 100),
            'status' => 'draft',
        ];
    }

    /**
     * Configure the transfer as in_transit with a specific delivery day.
     */
    public function inTransit(int $deliveryDay): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_transit',
            'delivery_day' => $deliveryDay,
        ]);
    }
}
