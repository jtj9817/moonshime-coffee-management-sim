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
        // Handle case where faker is not available (production without dev dependencies)
        $hasFaker = $this->faker !== null;

        $type = $hasFaker
            ? $this->faker->randomElement(['store', 'hub', 'warehouse', 'vendor'])
            : 'store';

        return [
            'name' => $this->generateName($type, $hasFaker),
            'address' => $hasFaker ? $this->faker->address() : 'Default Address',
            'max_storage' => $hasFaker ? $this->faker->numberBetween(100, 1000) : 500,
            'type' => $type,
        ];
    }

    /**
     * Generate a contextual name based on location type.
     */
    protected function generateName(string $type, bool $hasFaker = true): string
    {
        if (! $hasFaker) {
            return match ($type) {
                'store' => 'Default Coffee Shop',
                'hub' => 'Central Distribution Hub',
                'warehouse' => 'Central Depot',
                'vendor' => 'Default Imports',
                default => 'Default Location',
            };
        }

        return match ($type) {
            'store' => $this->faker->unique()->company().' Coffee',
            'hub' => $this->faker->unique()->city().' Distribution Hub',
            'warehouse' => $this->faker->unique()->city().' Depot',
            'vendor' => $this->faker->unique()->lastName().' Imports',
            default => $this->faker->unique()->company(),
        };
    }
}
