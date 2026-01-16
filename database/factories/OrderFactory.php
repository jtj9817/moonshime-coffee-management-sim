<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'status' => 'Draft',
            'total_cost' => fake()->numberBetween(1000, 10000),
            'delivery_date' => now()->addDays(3),
        ];
    }
}