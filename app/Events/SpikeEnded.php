<?php

namespace App\Events;

use App\Models\SpikeEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SpikeEnded
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public SpikeEvent $spike
    ) {}
}
