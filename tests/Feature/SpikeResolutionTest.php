<?php

use App\Models\GameState;
use App\Models\Location;
use App\Models\SpikeEvent;
use App\Models\SpikeResolution;
use App\Models\User;
use App\Services\SpikeResolutionService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->gameState = GameState::create([
        'user_id' => $this->user->id,
        'cash' => 100000, // 1000 dollars in cents
        'xp' => 0,
        'day' => 5,
    ]);
    $this->location = Location::factory()->create(['type' => 'warehouse']);
    $this->service = app(SpikeResolutionService::class);
});

test('can resolve breakdown spike early and deduct cost', function () {
    $spike = SpikeEvent::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'breakdown',
        'magnitude' => 0.5,
        'location_id' => $this->location->id,
        'is_active' => true,
        'starts_at_day' => 4,
        'ends_at_day' => 8,
    ]);

    $costBefore = $this->gameState->cash;
    $estimatedCost = $spike->resolution_cost_estimate;

    $this->service->resolveEarly($spike);

    $spike->refresh();
    $this->gameState->refresh();

    expect($spike->is_active)->toBeFalse()
        ->and($spike->resolved_by)->toBe('player')
        ->and($spike->resolved_at)->not()->toBeNull()
        ->and($spike->resolution_cost)->toBe($estimatedCost)
        ->and($spike->ends_at_day)->toBe(5) // Current day
        ->and($this->gameState->cash)->toBe($costBefore - $estimatedCost);
});

test('can resolve blizzard spike early', function () {
    $spike = SpikeEvent::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'blizzard',
        'magnitude' => 1.0,
        'is_active' => true,
        'starts_at_day' => 4,
        'ends_at_day' => 7,
    ]);

    $this->service->resolveEarly($spike);

    $spike->refresh();

    expect($spike->is_active)->toBeFalse()
        ->and($spike->resolved_by)->toBe('player')
        ->and($spike->resolved_at)->not()->toBeNull();
});

test('cannot resolve non-resolvable spike types', function () {
    $spike = SpikeEvent::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'demand',
        'magnitude' => 1.5,
        'is_active' => true,
    ]);

    $this->service->resolveEarly($spike);
})->throws(\InvalidArgumentException::class, 'cannot be resolved early');

test('cannot resolve with insufficient funds', function () {
    $this->gameState->update(['cash' => 10000]); // Very low cash (100 dollars in cents)

    $spike = SpikeEvent::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'breakdown',
        'magnitude' => 1.0,
        'is_active' => true,
    ]);

    $this->service->resolveEarly($spike);
})->throws(\RuntimeException::class, 'Insufficient funds');

test('can mitigate a spike', function () {
    $spike = SpikeEvent::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'demand',
        'is_active' => true,
    ]);

    $this->service->mitigate($spike, 'Ordered extra inventory');

    $spike->refresh();

    expect($spike->mitigated_at)->not()->toBeNull()
        ->and($spike->action_log)->toHaveCount(1)
        ->and($spike->action_log[0]['action'])->toBe('Ordered extra inventory');
});

test('can acknowledge a spike', function () {
    $spike = SpikeEvent::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'price',
        'is_active' => true,
    ]);

    expect($spike->acknowledged_at)->toBeNull();

    $this->service->acknowledge($spike);

    $spike->refresh();

    expect($spike->acknowledged_at)->not()->toBeNull()
        ->and($spike->action_log)->toHaveCount(1)
        ->and($spike->action_log[0]['action'])->toBe('acknowledged');
});

test('resolveEarly creates a SpikeResolution audit record', function () {
    $spike = SpikeEvent::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'breakdown',
        'magnitude' => 0.5,
        'location_id' => $this->location->id,
        'is_active' => true,
        'starts_at_day' => 4,
        'ends_at_day' => 8,
    ]);

    $this->service->resolveEarly($spike);

    $resolution = SpikeResolution::where('spike_event_id', $spike->id)->first();

    expect($resolution)->not()->toBeNull()
        ->and($resolution->user_id)->toBe($this->user->id)
        ->and($resolution->action_type)->toBe('resolve_early')
        ->and($resolution->cost_cents)->toBeGreaterThan(0)
        ->and($resolution->game_day)->toBe(5)
        ->and($resolution->effect)->toBeArray()
        ->and($resolution->effect['spike_deactivated'])->toBeTrue();
});

test('mitigate creates a SpikeResolution audit record', function () {
    $spike = SpikeEvent::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'demand',
        'is_active' => true,
    ]);

    $this->service->mitigate($spike, 'Ordered extra inventory');

    $resolution = SpikeResolution::where('spike_event_id', $spike->id)->first();

    expect($resolution)->not()->toBeNull()
        ->and($resolution->action_type)->toBe('mitigate')
        ->and($resolution->action_detail)->toBe('Ordered extra inventory')
        ->and($resolution->cost_cents)->toBe(0);
});

test('acknowledge creates a SpikeResolution audit record', function () {
    $spike = SpikeEvent::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'price',
        'is_active' => true,
    ]);

    $this->service->acknowledge($spike);

    $resolution = SpikeResolution::where('spike_event_id', $spike->id)->first();

    expect($resolution)->not()->toBeNull()
        ->and($resolution->action_type)->toBe('acknowledge');
});

test('acknowledging twice does not duplicate log', function () {
    $spike = SpikeEvent::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'delay',
        'is_active' => true,
    ]);

    $this->service->acknowledge($spike);
    $spike->refresh();

    $this->service->acknowledge($spike);
    $spike->refresh();

    expect($spike->action_log)->toHaveCount(1);
});
