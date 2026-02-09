<?php

namespace App\Listeners;

use App\Events\SpikeOccurred;
use App\Events\TimeAdvanced;
use App\Services\SpikeEventFactory;

class GenerateSpike
{
    /**
     * Create the event listener.
     */
    public function __construct(
        protected SpikeEventFactory $factory
    ) {}

    /**
     * Handle the event.
     */
    public function onTimeAdvanced(TimeAdvanced $event): void
    {
        $spike = $this->factory->generate($event->day);

        if ($spike) {
            event(new SpikeOccurred($spike));
        }
    }
}
