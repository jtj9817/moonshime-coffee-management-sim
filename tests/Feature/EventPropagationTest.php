<?php

use App\Events\SpikeEnded;
use App\Events\SpikeOccurred;
use App\Models\Route;
use App\Models\SpikeEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('blizzard event disables vulnerable route and restores it on end', function () {
    // 1. Setup
    $route = Route::factory()->create([
        'weather_vulnerability' => true,
        'is_active' => true,
    ]);

    // Create a Blizzard Spike targeting this route
    $spike = SpikeEvent::factory()->create([
        'type' => 'blizzard',
        'affected_route_id' => $route->id,
        'is_active' => true,
    ]);

    // 2. Act: Trigger SpikeOccurred
    event(new SpikeOccurred($spike));

    // 3. Assert: Route should be inactive
    expect($route->fresh()->is_active)->toBeFalse();

    // 4. Act: Trigger SpikeEnded
    event(new SpikeEnded($spike));

    // 5. Assert: Route should be active again
    expect($route->fresh()->is_active)->toBeTrue();
});
