<?php

use App\Events\SpikeOccurred;
use App\Events\TimeAdvanced;
use App\Listeners\GenerateSpike;
use App\Models\GameState;
use App\Models\SpikeEvent;
use App\Services\SpikeEventFactory;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;

test('it generates a spike when time advances', function () {
    Event::fake([SpikeOccurred::class]);

    $spike = SpikeEvent::factory()->make(); // Don't persist yet, or persist if needed

    $factory = Mockery::mock(SpikeEventFactory::class, function (MockInterface $mock) use ($spike) {
        $mock->shouldReceive('generate')->once()->with(2)->andReturn($spike);
    });

    $listener = new GenerateSpike($factory);
    $listener->onTimeAdvanced(new TimeAdvanced(2, GameState::factory()->make()));

    Event::assertDispatched(SpikeOccurred::class, function ($event) use ($spike) {
        return $event->spike === $spike;
    });
});

test('it does not fire SpikeOccurred if no spike generated', function () {
    Event::fake([SpikeOccurred::class]);

    $factory = Mockery::mock(SpikeEventFactory::class, function (MockInterface $mock) {
        $mock->shouldReceive('generate')->once()->andReturn(null);
    });

    $listener = new GenerateSpike($factory);
    $listener->onTimeAdvanced(new TimeAdvanced(2, GameState::factory()->make()));

    Event::assertNotDispatched(SpikeOccurred::class);
});
