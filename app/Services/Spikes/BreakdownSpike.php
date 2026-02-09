<?php

namespace App\Services\Spikes;

use App\Interfaces\SpikeTypeInterface;
use App\Models\Location;
use App\Models\SpikeEvent;

class BreakdownSpike implements SpikeTypeInterface
{
    public function apply(SpikeEvent $event): void
    {
        if (! $event->location_id) {
            return;
        }

        $location = Location::find($event->location_id);
        if (! $location) {
            return;
        }

        $meta = $event->meta ?? [];
        $meta['original_max_storage'] = $location->max_storage;

        // Reduce capacity by magnitude (percentage, e.g. 0.5 = 50% reduction)
        $newCapacity = (int) ($location->max_storage * (1 - (float) $event->magnitude));

        $location->update(['max_storage' => $newCapacity]);
        $event->update(['meta' => $meta, 'is_active' => true]);
    }

    public function rollback(SpikeEvent $event): void
    {
        if (! $event->location_id) {
            return;
        }

        $location = Location::find($event->location_id);
        if (! $location) {
            return;
        }

        $meta = $event->meta;
        if (isset($meta['original_max_storage'])) {
            $location->update(['max_storage' => $meta['original_max_storage']]);
        }

        $event->update(['is_active' => false]);
    }
}
