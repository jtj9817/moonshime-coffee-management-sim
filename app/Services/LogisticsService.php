<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Database\Eloquent\Collection;

class LogisticsService
{
    /**
     * Get all active routes between two locations.
     *
     * @param Location $source
     * @param Location $target
     * @return Collection
     */
    public function getValidRoutes(Location $source, Location $target): Collection
    {
        return $source->outgoingRoutes() // Assuming relationship exists, or query directly
            ->where('target_id', $target->id)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Calculate the cost of traversing a route.
     *
     * @param \App\Models\Route $route
     * @return int
     */
    public function calculateCost(\App\Models\Route $route): int
    {
        return $route->weights['cost'] ?? 0;
    }
}
