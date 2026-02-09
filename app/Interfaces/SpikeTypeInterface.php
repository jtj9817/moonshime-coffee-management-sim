<?php

namespace App\Interfaces;

use App\Models\SpikeEvent;

interface SpikeTypeInterface
{
    public function apply(SpikeEvent $event): void;

    public function rollback(SpikeEvent $event): void;
}
