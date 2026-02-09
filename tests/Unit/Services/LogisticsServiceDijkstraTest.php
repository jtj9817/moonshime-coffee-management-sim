<?php

use App\Models\Location;
use App\Models\Route;
use App\Services\LogisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('findBestRoute returns cheapest path', function () {
    // A -> B -> C (Cost 20)
    // A -> D -> C (Cost 55)
    $a = Location::factory()->create(['name' => 'A']);
    $b = Location::factory()->create(['name' => 'B']);
    $c = Location::factory()->create(['name' => 'C']);
    $d = Location::factory()->create(['name' => 'D']);

    // Path 1
    $r1 = Route::factory()->create(['source_id' => $a->id, 'target_id' => $b->id, 'cost' => 10, 'is_active' => true]);
    $r2 = Route::factory()->create(['source_id' => $b->id, 'target_id' => $c->id, 'cost' => 10, 'is_active' => true]);

    // Path 2
    $r3 = Route::factory()->create(['source_id' => $a->id, 'target_id' => $d->id, 'cost' => 50, 'is_active' => true]);
    $r4 = Route::factory()->create(['source_id' => $d->id, 'target_id' => $c->id, 'cost' => 5, 'is_active' => true]);

    $service = new LogisticsService;
    $path = $service->findBestRoute($a, $c);

    expect($path)->not->toBeNull();
    expect($path)->toHaveCount(2);
    expect($path[0]->id)->toBe($r1->id);
    expect($path[1]->id)->toBe($r2->id);
});

test('findBestRoute avoids inactive routes', function () {
    // A -> B -> C (B->C is inactive)
    // A -> D -> C (Cost 100)
    $a = Location::factory()->create(['name' => 'A']);
    $b = Location::factory()->create(['name' => 'B']);
    $c = Location::factory()->create(['name' => 'C']);
    $d = Location::factory()->create(['name' => 'D']);

    // Path 1 (Blocked)
    Route::factory()->create(['source_id' => $a->id, 'target_id' => $b->id, 'cost' => 10, 'is_active' => true]);
    Route::factory()->create(['source_id' => $b->id, 'target_id' => $c->id, 'cost' => 10, 'is_active' => false]);

    // Path 2 (Expensive but active)
    $r3 = Route::factory()->create(['source_id' => $a->id, 'target_id' => $d->id, 'cost' => 50, 'is_active' => true]);
    $r4 = Route::factory()->create(['source_id' => $d->id, 'target_id' => $c->id, 'cost' => 50, 'is_active' => true]);

    $service = new LogisticsService;
    $path = $service->findBestRoute($a, $c);

    expect($path)->toHaveCount(2);
    expect($path[0]->id)->toBe($r3->id);
    expect($path[1]->id)->toBe($r4->id);
});
