<?php

use App\Models\GameState;
use App\Models\Location;
use App\Models\SpikeEvent;
use App\Models\User;
use App\Services\GuaranteedSpikeGenerator;
use App\Services\SpikeConstraintChecker;
use App\Services\SpikeEventFactory;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->gameState = GameState::create([
        'user_id' => $this->user->id,
        'cash' => 10000.00,
        'xp' => 0,
        'day' => 1,
    ]);
    // Create a location for spikes that need it
    Location::factory()->create(['type' => 'warehouse']);
    
    $this->generator = app(GuaranteedSpikeGenerator::class);
});

test('generates spike starting on current day', function () {
    $spike = $this->generator->generate($this->gameState, 2);

    expect($spike)->not->toBeNull();
    expect($spike->starts_at_day)->toBe(2);
    expect($spike->is_guaranteed)->toBeTrue();
    expect($spike->user_id)->toBe($this->user->id);
});

test('returns null on day 1 (tutorial grace period)', function () {
    $spike = $this->generator->generate($this->gameState, 1);

    expect($spike)->toBeNull();
});

test('returns null when spike window would exceed cap', function () {
    // Create 2 spikes covering the target day
    SpikeEvent::factory()->create([
        'user_id' => $this->user->id,
        'starts_at_day' => 2,
        'ends_at_day' => 8,
        'is_active' => false,
    ]);
    SpikeEvent::factory()->create([
        'user_id' => $this->user->id,
        'starts_at_day' => 2,
        'ends_at_day' => 8,
        'is_active' => false,
    ]);

    $spike = $this->generator->generate($this->gameState, 2);

    expect($spike)->toBeNull();
});

test('respects type cooldown constraints', function () {
    // Set all types except 'price' on cooldown
    $this->gameState->update(['spike_cooldowns' => [
        'demand' => 3,
        'delay' => 3,
        'breakdown' => 3,
        'blizzard' => 3,
    ]]);
    $this->gameState->refresh();

    // Generate on day 4 - only price should be available (within 2-day cooldown)
    $spike = $this->generator->generate($this->gameState, 4);

    expect($spike)->not->toBeNull();
    expect($spike->type)->toBe('price');
});

test('falls back to available type when all on cooldown', function () {
    // Set all types on cooldown
    $this->gameState->update(['spike_cooldowns' => [
        'demand' => 3,
        'delay' => 3,
        'price' => 3,
        'breakdown' => 3,
        'blizzard' => 3,
    ]]);
    $this->gameState->refresh();

    // Should still generate (cooldown relaxed as fallback)
    $spike = $this->generator->generate($this->gameState, 4);

    expect($spike)->not->toBeNull();
    expect($spike->is_guaranteed)->toBeTrue();
});
