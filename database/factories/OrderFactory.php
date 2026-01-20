<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
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
            'user_id' => User::factory(),
            'vendor_id' => Vendor::factory(),
            'status' => 'draft',
            'total_cost' => fake()->randomFloat(2, 10, 100),
            'delivery_date' => now()->addDays(3),
        ];
    }

    /**
     * Configure the order as shipped with a specific delivery day.
     */
    public function shipped(int $deliveryDay): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'shipped',
            'delivery_day' => $deliveryDay,
        ]);
    }
}
