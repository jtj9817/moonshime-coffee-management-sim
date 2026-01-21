<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Location>
 */
class LocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['store', 'hub', 'warehouse', 'vendor']);

        return [
            'name' => $this->generateName($type),
            'address' => $this->faker->address(),
            'max_storage' => $this->faker->numberBetween(100, 1000),
            'type' => $type,
        ];
    }

    /**
     * Generate a contextual name based on location type.
     */
    protected function generateName(string $type): string
    {
        return match ($type) {
            'store' => $this->faker->unique()->company() . ' Coffee',
            'hub' => $this->faker->unique()->city() . ' Distribution Hub',
            'warehouse' => $this->faker->unique()->city() . ' Depot',
            'vendor' => $this->faker->unique()->lastName() . ' Imports',
            default => $this->faker->unique()->company(),
        };
    }
}
