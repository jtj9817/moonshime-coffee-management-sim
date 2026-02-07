<?php

namespace Database\Factories;

use App\Models\DemandEvent;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DemandEvent>
 */
class DemandEventFactory extends Factory
{
    protected $model = DemandEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $requested = $this->faker->numberBetween(1, 20);
        $fulfilled = $this->faker->numberBetween(0, $requested);
        $lost = $requested - $fulfilled;
        $unitPrice = $this->faker->numberBetween(100, 1000); // cents

        return [
            'user_id' => User::factory(),
            'day' => $this->faker->numberBetween(1, 30),
            'location_id' => Location::factory(),
            'product_id' => Product::factory(),
            'requested_quantity' => $requested,
            'fulfilled_quantity' => $fulfilled,
            'lost_quantity' => $lost,
            'unit_price' => $unitPrice,
            'revenue' => $fulfilled * $unitPrice,
            'lost_revenue' => $lost * $unitPrice,
        ];
    }
}
