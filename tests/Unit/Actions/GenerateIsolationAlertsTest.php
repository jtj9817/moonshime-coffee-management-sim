<?php

use App\Actions\GenerateIsolationAlerts;
use App\Models\Alert;
use App\Models\Location;
use App\Models\SpikeEvent;
use App\Models\User;
use App\Services\LogisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('generates isolation alert when store is unreachable and stock is low', function () {
    // Setup
    $user = User::factory()->create();
    $store = Location::factory()->create(['type' => 'store']);
    $blizzard = SpikeEvent::factory()->create(['type' => 'blizzard', 'is_active' => true, 'user_id' => $user->id]);

    // Add low stock inventory
    \App\Models\Inventory::factory()->create([
        'location_id' => $store->id,
        'quantity' => 5,
        'user_id' => $user->id,
    ]);

    // Mock LogisticsService to return false for reachability
    $logistics = Mockery::mock(LogisticsService::class);
    $logistics->shouldReceive('checkReachability')->with(Mockery::on(fn ($loc) => $loc->id === $store->id))->andReturn(false);
    // For any other locations (like the one created by SpikeEventFactory), return true to avoid extra alerts
    $logistics->shouldReceive('checkReachability')->andReturn(true);

    $action = new GenerateIsolationAlerts($logistics);
    $action->handle($user->id);

    // Assert
    expect(Alert::where('type', 'isolation')->count())->toBe(1);
    $alert = Alert::where('type', 'isolation')->first();
    expect($alert->location_id)->toBe($store->id);
    expect($alert->spike_event_id)->toBe($blizzard->id);
});

test('does not generate alert if store is unreachable but stock is healthy', function () {
    $user = User::factory()->create();
    $store = Location::factory()->create(['type' => 'store']);
    \App\Models\Inventory::factory()->create([
        'location_id' => $store->id,
        'quantity' => 50,
        'user_id' => $user->id,
    ]);

    $logistics = Mockery::mock(LogisticsService::class);
    $logistics->shouldReceive('checkReachability')->andReturn(false);

    $action = new GenerateIsolationAlerts($logistics);
    $action->handle($user->id);

    expect(Alert::where('type', 'isolation')->count())->toBe(0);
});

test('does not generate alert if store is reachable', function () {
    $user = User::factory()->create();
    $store = Location::factory()->create(['type' => 'store']);
    \App\Models\Inventory::factory()->create([
        'location_id' => $store->id,
        'quantity' => 5,
        'user_id' => $user->id,
    ]);

    $logistics = Mockery::mock(LogisticsService::class);
    $logistics->shouldReceive('checkReachability')->andReturn(true);

    $action = new GenerateIsolationAlerts($logistics);
    $action->handle($user->id);

    expect(Alert::where('type', 'isolation')->count())->toBe(0);
});
