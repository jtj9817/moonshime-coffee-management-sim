<?php

use App\Events\SpikeEnded;
use App\Events\SpikeOccurred;
use App\Listeners\ApplySpikeEffect;
use App\Listeners\RollbackSpikeEffect;
use App\Models\SpikeEvent;
use App\Services\SpikeEventFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('ApplySpikeEffect calls factory apply', function () {
    $spike = SpikeEvent::factory()->create();
    $event = new SpikeOccurred($spike);

    $factory = Mockery::mock(SpikeEventFactory::class);
    $factory->shouldReceive('apply')->once()->with($spike);

    $listener = new ApplySpikeEffect($factory);
    $listener->handle($event);
});

test('RollbackSpikeEffect calls factory rollback', function () {
    $spike = SpikeEvent::factory()->create();
    $event = new SpikeEnded($spike);

    $factory = Mockery::mock(SpikeEventFactory::class);
    $factory->shouldReceive('rollback')->once()->with($spike);

    $listener = new RollbackSpikeEffect($factory);
    $listener->handle($event);
});
