<?php

use App\Models\GameState;
use App\Models\Location;
use App\Models\Route;
use App\Models\SpikeEvent;
use App\Models\User;
use App\Services\SpikeResolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->gameState = GameState::create([
        'user_id' => $this->user->id,
        'cash' => 100000, // 1000 dollars in cents
        'xp' => 0,
        'day' => 5,
    ]);
    $this->service = app(SpikeResolutionService::class);
});

describe('Breakdown Spike Resolution', function () {
    test('resolving breakdown spike restores storage capacity', function () {
        $location = Location::factory()->create([
            'type' => 'warehouse',
            'max_storage' => 1000,
        ]);

        $spike = SpikeEvent::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'breakdown',
            'magnitude' => 0.5, // 50% reduction
            'location_id' => $location->id,
            'is_active' => true,
            'starts_at_day' => 4,
            'ends_at_day' => 8,
            'meta' => ['original_max_storage' => 1000],
        ]);

        // Simulate the breakdown effect (capacity should be reduced)
        $location->update(['max_storage' => 500]);

        // Resolve the spike
        $this->service->resolveEarly($spike);

        // Verify storage is restored
        $location->refresh();
        expect($location->max_storage)->toBe(1000);
    });

    test('breakdown resolution deducts cash correctly', function () {
        $location = Location::factory()->create([
            'type' => 'warehouse',
            'max_storage' => 1000,
        ]);

        $spike = SpikeEvent::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'breakdown',
            'magnitude' => 0.5,
            'location_id' => $location->id,
            'is_active' => true,
            'starts_at_day' => 4,
            'ends_at_day' => 8,
            'meta' => ['original_max_storage' => 1000],
        ]);

        $location->update(['max_storage' => 500]);
        $cashBefore = $this->gameState->cash;
        $expectedCost = $spike->resolution_cost_estimate;

        $this->service->resolveEarly($spike);

        $this->gameState->refresh();
        expect($this->gameState->cash)->toBe($cashBefore - $expectedCost);
    });
});

describe('Blizzard Spike Resolution', function () {
    test('resolving blizzard spike reactivates route', function () {
        $sourceLocation = Location::factory()->create(['type' => 'hub']);
        $targetLocation = Location::factory()->create(['type' => 'warehouse']);

        $route = Route::factory()->create([
            'source_id' => $sourceLocation->id,
            'target_id' => $targetLocation->id,
            'is_active' => false, // Route is blocked by blizzard
        ]);

        $spike = SpikeEvent::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'blizzard',
            'magnitude' => 1.0,
            'affected_route_id' => $route->id,
            'is_active' => true,
            'starts_at_day' => 4,
            'ends_at_day' => 7,
        ]);

        // Resolve the spike
        $this->service->resolveEarly($spike);

        // Verify route is reactivated
        $route->refresh();
        expect($route->is_active)->toBeTrue();
    });

    test('blizzard resolution updates spike tracking fields', function () {
        $sourceLocation = Location::factory()->create(['type' => 'hub']);
        $targetLocation = Location::factory()->create(['type' => 'warehouse']);

        $route = Route::factory()->create([
            'source_id' => $sourceLocation->id,
            'target_id' => $targetLocation->id,
            'is_active' => false,
        ]);

        $spike = SpikeEvent::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'blizzard',
            'magnitude' => 1.0,
            'affected_route_id' => $route->id,
            'is_active' => true,
            'starts_at_day' => 4,
            'ends_at_day' => 7,
        ]);

        $this->service->resolveEarly($spike);

        $spike->refresh();
        expect($spike->is_active)->toBeFalse()
            ->and($spike->resolved_by)->toBe('player')
            ->and($spike->resolved_at)->not()->toBeNull()
            ->and($spike->resolution_cost)->toBeGreaterThan(0)
            ->and($spike->ends_at_day)->toBe(5); // Current day
    });
});

describe('HTTP Endpoint', function () {
    test('POST /game/spikes/{spike}/resolve resolves spike successfully', function () {
        $location = Location::factory()->create([
            'type' => 'warehouse',
            'max_storage' => 1000,
        ]);

        $spike = SpikeEvent::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'breakdown',
            'magnitude' => 0.5,
            'location_id' => $location->id,
            'is_active' => true,
            'starts_at_day' => 4,
            'ends_at_day' => 8,
            'meta' => ['original_max_storage' => 1000],
        ]);

        $location->update(['max_storage' => 500]);

        $response = $this->actingAs($this->user)
            ->post("/game/spikes/{$spike->id}/resolve");

        $response->assertRedirect();

        $spike->refresh();
        expect($spike->is_active)->toBeFalse()
            ->and($spike->resolved_by)->toBe('player');

        $location->refresh();
        expect($location->max_storage)->toBe(1000);
    });

    test('cannot resolve spike belonging to another user', function () {
        $otherUser = User::factory()->create();
        GameState::create([
            'user_id' => $otherUser->id,
            'cash' => 100000,
            'xp' => 0,
            'day' => 5,
        ]);

        $spike = SpikeEvent::factory()->create([
            'user_id' => $otherUser->id,
            'type' => 'breakdown',
            'magnitude' => 0.5,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->post("/game/spikes/{$spike->id}/resolve");

        $response->assertForbidden();
    });

    test('cannot resolve non-resolvable spike type via endpoint', function () {
        $spike = SpikeEvent::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'demand',
            'magnitude' => 1.5,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->post("/game/spikes/{$spike->id}/resolve");

        // Controller redirects back with error flash message
        $response->assertRedirect();
        $response->assertSessionHas('error');
    });
});
