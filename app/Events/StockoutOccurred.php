<?php

namespace App\Events;

use App\Models\DemandEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockoutOccurred
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public DemandEvent $demandEvent
    ) {}
}
