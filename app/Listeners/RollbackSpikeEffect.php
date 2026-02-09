<?php

namespace App\Listeners;

use App\Events\SpikeEnded;
use App\Services\SpikeEventFactory;

class RollbackSpikeEffect
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
    public function handle(SpikeEnded $event): void
    {
        $this->factory->rollback($event->spike);
    }
}
