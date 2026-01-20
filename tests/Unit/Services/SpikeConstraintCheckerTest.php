<?php

use App\Models\GameState;
use App\Models\SpikeEvent;
use App\Models\User;
use App\Services\SpikeConstraintChecker;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->gameState = GameState::create([
        'user_id' => $this->user->id,
        'cash' => 10000.00,
        'xp' => 0,
        'day' => 1,
    ]);
    $this->checker = new SpikeConstraintChecker();
});

test('canScheduleSpike returns false when window would exceed cap', function () {
    // Create 2 spikes covering days 3-5
    SpikeEvent::factory()->create([
        'user_id' => $this->user->id,
        'starts_at_day' => 3,
        'ends_at_day' => 6,
        'is_active' => false,
    ]);
    SpikeEvent::factory()->create([
        'user_id' => $this->user->id,
        'starts_at_day' => 4,
        'ends_at_day' => 7,
        'is_active' => false,
    ]);

    // Trying to schedule a spike for day 4 should fail (already 2 spikes covering day 4)
    expect($this->checker->canScheduleSpike($this->gameState, 4, 2))->toBeFalse();
});

test('canScheduleSpike returns true when window fits cap', function () {
    // Create 1 spike covering days 3-5
    SpikeEvent::factory()->create([
        'user_id' => $this->user->id,
        'starts_at_day' => 3,
        'ends_at_day' => 6,
        'is_active' => false,
    ]);

    // Scheduling another spike is fine (only 1 currently exists)
    expect($this->checker->canScheduleSpike($this->gameState, 4, 2))->toBeTrue();
});

test('getSpikeCountCoveringDay counts scheduled + active spikes', function () {
    // Inactive spike scheduled for future
    SpikeEvent::factory()->create([
        'user_id' => $this->user->id,
        'starts_at_day' => 3,
        'ends_at_day' => 6,
        'is_active' => false,
    ]);

    // Active spike
    SpikeEvent::factory()->create([
        'user_id' => $this->user->id,
        'starts_at_day' => 2,
        'ends_at_day' => 5,
        'is_active' => true,
    ]);

    // Both should be counted for day 4
    expect($this->checker->getSpikeCountCoveringDay($this->user->id, 4))->toBe(2);
    // Only one for day 5 (second spike ends at 5, which means ends_at_day > 5 fails)
    expect($this->checker->getSpikeCountCoveringDay($this->user->id, 5))->toBe(1);
});

test('getAllowedTypes excludes types in cooldown window', function () {
    // Set cooldowns: demand started on day 3, delay started on day 4
    $this->gameState->update(['spike_cooldowns' => ['demand' => 3, 'delay' => 4]]);
    $this->gameState->refresh();

    // On day 5: demand (5-3=2 within cooldown), delay (5-4=1 within cooldown)
    $allowed = $this->checker->getAllowedTypes($this->gameState, 5);

    expect($allowed)->not->toContain('demand');
    expect($allowed)->not->toContain('delay');
    expect($allowed)->toContain('price');
    expect($allowed)->toContain('breakdown');
    expect($allowed)->toContain('blizzard');
});

test('getAllowedTypes returns all types when no cooldown', function () {
    $allowed = $this->checker->getAllowedTypes($this->gameState, 5);

    expect($allowed)->toContain('demand');
    expect($allowed)->toContain('delay');
    expect($allowed)->toContain('price');
    expect($allowed)->toContain('breakdown');
    expect($allowed)->toContain('blizzard');
});

test('getAllowedTypes excludes recently scheduled spike types', function () {
    // Create a demand spike scheduled to start on day 4
    SpikeEvent::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'demand',
        'starts_at_day' => 4,
        'ends_at_day' => 7,
        'is_active' => false,
    ]);

    // On day 5, demand should be excluded (4 is within 2-day window of 5)
    $allowed = $this->checker->getAllowedTypes($this->gameState, 5);

    expect($allowed)->not->toContain('demand');
});

test('getAllowedTypes excludes future scheduled spike types', function () {
    // Create a demand spike scheduled to start on day 6
    SpikeEvent::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'demand',
        'starts_at_day' => 6,
        'ends_at_day' => 9,
        'is_active' => false,
    ]);

    // On day 5, demand should be excluded (6 is within 2-day window of 5)
    $allowed = $this->checker->getAllowedTypes($this->gameState, 5);

    expect($allowed)->not->toContain('demand');
});

test('recordSpikeStarted updates cooldown tracking', function () {
    $this->checker->recordSpikeStarted($this->gameState, 'demand', 5);
    $this->gameState->refresh();

    expect($this->gameState->spike_cooldowns)->toHaveKey('demand');
    expect($this->gameState->spike_cooldowns['demand'])->toBe(5);

    // Record another type
    $this->checker->recordSpikeStarted($this->gameState, 'blizzard', 7);
    $this->gameState->refresh();

    expect($this->gameState->spike_cooldowns)->toHaveKey('blizzard');
    expect($this->gameState->spike_cooldowns['blizzard'])->toBe(7);
    // demand should still be there
    expect($this->gameState->spike_cooldowns['demand'])->toBe(5);
});
