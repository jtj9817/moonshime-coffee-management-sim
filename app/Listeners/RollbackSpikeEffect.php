<?php

namespace App\Listeners;

use App\Events\SpikeEnded;
use App\Services\SpikeEventFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

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