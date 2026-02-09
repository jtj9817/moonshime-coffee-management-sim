<?php

use App\Models\Location;
use App\Models\Route;
use App\Models\User;

use function Pest\Laravel\actingAs;

test('authenticated users can retrieve routes', function () {
    $user = User::factory()->create();

    // Create some locations and routes
    $locA = Location::factory()->create(['name' => 'Location A']);
    $locB = Location::factory()->create(['name' => 'Location B']);
    $locC = Location::factory()->create(['name' => 'Location C']);

    Route::factory()->create(['source_id' => $locA->id, 'target_id' => $locB->id]);
    Route::factory()->create(['source_id' => $locB->id, 'target_id' => $locC->id]);

    actingAs($user)
        ->getJson('/game/logistics/routes')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'source_id',
                    'target_id',
                    'transport_mode',
                    'cost',
                    'transit_days',
                    'is_active',
                ],
            ],
        ]);
});

test('users can filter routes by source', function () {
    $user = User::factory()->create();

    $locA = Location::factory()->create();
    $locB = Location::factory()->create();
    $locC = Location::factory()->create();

    Route::factory()->create(['source_id' => $locA->id, 'target_id' => $locB->id]);
    Route::factory()->create(['source_id' => $locC->id, 'target_id' => $locB->id]);

    actingAs($user)
        ->getJson("/game/logistics/routes?source_id={$locA->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.source_id', $locA->id);
});

test('users can filter routes by target', function () {
    $user = User::factory()->create();

    $locA = Location::factory()->create();
    $locB = Location::factory()->create();
    $locC = Location::factory()->create();

    Route::factory()->create(['source_id' => $locA->id, 'target_id' => $locB->id]);
    Route::factory()->create(['source_id' => $locA->id, 'target_id' => $locC->id]);

    actingAs($user)
        ->getJson("/game/logistics/routes?target_id={$locB->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.target_id', $locB->id);
});

test('routes include location details', function () {
    $user = User::factory()->create();

    $locA = Location::factory()->create(['name' => 'Origin']);
    $locB = Location::factory()->create(['name' => 'Dest']);

    Route::factory()->create(['source_id' => $locA->id, 'target_id' => $locB->id]);

    actingAs($user)
        ->getJson('/game/logistics/routes')
        ->assertOk()
        ->assertJsonPath('data.0.source.name', 'Origin')
        ->assertJsonPath('data.0.target.name', 'Dest');
});
