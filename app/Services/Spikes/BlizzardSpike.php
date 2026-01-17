<?php

namespace App\Services\Spikes;

use App\Interfaces\SpikeTypeInterface;
use App\Models\SpikeEvent;

class BlizzardSpike implements SpikeTypeInterface
{
    public function apply(SpikeEvent $event): void
    {
        $route = $event->affectedRoute;
        if ($route) {
            $route->update(['is_active' => false]);
        }
    }

    public function rollback(SpikeEvent $event): void
    {
        $route = $event->affectedRoute;
        if ($route) {
            $route->update(['is_active' => true]);
        }
    }
}
