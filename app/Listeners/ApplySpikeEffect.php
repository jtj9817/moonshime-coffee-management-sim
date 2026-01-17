<?php

namespace App\Listeners;

use App\Events\SpikeOccurred;
use App\Services\SpikeEventFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ApplySpikeEffect
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
    public function handle(SpikeOccurred $event): void
    {
        $this->factory->apply($event->spike);
    }
}