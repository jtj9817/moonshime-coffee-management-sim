<?php

namespace App\Services;

use App\Models\SpikeEvent;
use App\Models\Location;
use App\Models\Product;
use App\Services\Spikes\DemandSpike;
use App\Services\Spikes\DelaySpike;
use App\Services\Spikes\PriceSpike;
use App\Services\Spikes\BreakdownSpike;
use App\Interfaces\SpikeTypeInterface;
use Exception;

class SpikeEventFactory
{
    protected array $weights = [
        'demand' => 40,
        'delay' => 20,
        'price' => 30,
        'breakdown' => 10,
    ];

    /**
     * Generate a new random spike event.
     */
    public function generate(int $currentDay): ?SpikeEvent
    {
        $type = $this->getRandomType();
        
        $duration = rand(2, 5);
        $magnitude = $this->generateMagnitude($type);
        
        $locationId = null;
        if ($type === 'breakdown' || (rand(0, 100) > 50)) {
            $locationId = Location::inRandomOrder()->first()?->id;
            
            // Breakdown MUST have a location
            if ($type === 'breakdown' && !$locationId) {
                return null;
            }
        }

        $productId = null;
        if ($type !== 'breakdown' && (rand(0, 100) > 50)) {
            $productId = Product::inRandomOrder()->first()?->id;
        }

        return SpikeEvent::create([
            'type' => $type,
            'magnitude' => $magnitude,
            'duration' => $duration,
            'location_id' => $locationId,
            'product_id' => $productId,
            'starts_at_day' => $currentDay + 1,
            'ends_at_day' => $currentDay + 1 + $duration,
            'is_active' => false,
        ]);
    }

    /**
     * Apply the effect of a spike event.
     */
    public function apply(SpikeEvent $event): void
    {
        $this->getImplementation($event->type)->apply($event);
    }

    /**
     * Rollback the effect of a spike event.
     */
    public function rollback(SpikeEvent $event): void
    {
        $this->getImplementation($event->type)->rollback($event);
    }

    /**
     * Get the implementation for a spike type.
     */
    public function getImplementation(string $type): SpikeTypeInterface
    {
        return match ($type) {
            'demand' => new DemandSpike(),
            'delay' => new DelaySpike(),
            'price' => new PriceSpike(),
            'breakdown' => new BreakdownSpike(),
            default => throw new Exception("Unknown spike type: {$type}"),
        };
    }

    protected function getRandomType(): string
    {
        $totalWeight = array_sum($this->weights);
        $rand = rand(1, $totalWeight);
        
        $currentWeight = 0;
        foreach ($this->weights as $type => $weight) {
            $currentWeight += $weight;
            if ($rand <= $currentWeight) {
                return $type;
            }
        }
        
        return array_key_last($this->weights);
    }

    protected function generateMagnitude(string $type): float
    {
        return match ($type) {
            'demand' => 1.0 + (rand(20, 100) / 100), // 1.2 to 2.0
            'delay' => (float)rand(1, 3), // 1 to 3 days
            'price' => 1.0 + (rand(10, 50) / 100), // 1.1 to 1.5
            'breakdown' => rand(20, 70) / 100, // 20% to 70% reduction
            default => 1.0,
        };
    }
}
