<?php

use App\Events\TimeAdvanced;
use App\Models\GameState;
use App\Models\User;
use App\Services\SimulationService;
use Illuminate\Support\Facades\Event;

test('advanceTime increments the day in GameState', function () {
    $user = User::factory()->create();
    $gameState = GameState::factory()->create([
        'user_id' => $user->id,
        'day' => 1,
    ]);

    $service = new SimulationService($gameState);
    $service->advanceTime();

    expect($gameState->refresh()->day)->toBe(2);
});

test('advanceTime fires TimeAdvanced event', function () {
    Event::fake();

    $user = User::factory()->create();
    $gameState = GameState::factory()->create([
        'user_id' => $user->id,
        'day' => 1,
    ]);

    $service = new SimulationService($gameState);
    $service->advanceTime();

    Event::assertDispatched(TimeAdvanced::class, function ($event) {
        return $event->day === 2;
    });
});
