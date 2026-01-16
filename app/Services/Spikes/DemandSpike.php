<?php

namespace App\Services\Spikes;

use App\Interfaces\SpikeTypeInterface;
use App\Models\SpikeEvent;

class DemandSpike implements SpikeTypeInterface
{
    public function apply(SpikeEvent $event): void
    {
        $event->update(['is_active' => true]);
    }

    public function rollback(SpikeEvent $event): void
    {
        $event->update(['is_active' => false]);
    }
}
