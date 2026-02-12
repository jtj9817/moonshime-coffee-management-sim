<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Location>
 */
class LocationFactory extends Factory
{
    /**
     * Global sequence counter for test determinism.
     */
    protected static int $sequence = 0;

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
        self::$sequence++;
        $suffix = ' ' . str_pad((string) self::$sequence, 3, '0', STR_PAD_LEFT);

        if (! $hasFaker) {
            return match ($type) {
                'store' => 'Default Coffee Shop'.$suffix,
                'hub' => 'Central Distribution Hub'.$suffix,
                'warehouse' => 'Central Depot'.$suffix,
                'vendor' => 'Default Imports'.$suffix,
                default => 'Default Location'.$suffix,
            };
        }

        return match ($type) {
            'store' => $this->faker->company().' Coffee'.$suffix,
            'hub' => $this->faker->city().' Distribution Hub'.$suffix,
            'warehouse' => $this->faker->city().' Depot'.$suffix,
            'vendor' => $this->faker->lastName().' Imports'.$suffix,
            default => $this->faker->company().$suffix,
        };
    }
}
