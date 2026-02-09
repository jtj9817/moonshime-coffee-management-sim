<?php

use App\Models\Location;
use App\Models\Product;
use App\Models\SpikeEvent;

test('spike event model can be created', function () {
    $location = Location::factory()->create();
    $product = Product::factory()->create();

    $spike = SpikeEvent::create([
        'type' => 'demand',
        'magnitude' => 1.5,
        'duration' => 3,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'starts_at_day' => 10,
        'ends_at_day' => 13,
        'is_active' => true,
    ]);

    expect($spike->exists)->toBeTrue();
    expect($spike->type)->toBe('demand');
    expect($spike->location_id)->toBe($location->id);
    expect($spike->product_id)->toBe($product->id);
});

test('spike event factory generates events', function () {
    Location::factory()->count(3)->create();
    Product::factory()->count(3)->create();

    // Create at least one vulnerable route for blizzard spikes
    $locations = Location::all();
    \App\Models\Route::factory()->create([
        'source_id' => $locations[0]->id,
        'target_id' => $locations[1]->id,
        'weather_vulnerability' => true,
    ]);

    $factory = new \App\Services\SpikeEventFactory;
    $spike = $factory->generate(1);

    expect($spike)->toBeInstanceOf(SpikeEvent::class);
    expect($spike->starts_at_day)->toBe(2);
});

test('breakdown spike reduces location capacity and rolls back', function () {
    $location = Location::factory()->create(['max_storage' => 1000]);

    $spike = SpikeEvent::create([
        'type' => 'breakdown',
        'magnitude' => 0.4, // 40% reduction
        'duration' => 2,
        'location_id' => $location->id,
        'starts_at_day' => 5,
        'ends_at_day' => 7,
    ]);

    $factory = new \App\Services\SpikeEventFactory;

    // Apply
    $factory->apply($spike);

    $location->refresh();
    expect($location->max_storage)->toBe(600); // 1000 * (1 - 0.4)
    expect($spike->refresh()->meta['original_max_storage'])->toBe(1000);

    // Rollback
    $factory->rollback($spike);

    $location->refresh();
    expect($location->max_storage)->toBe(1000);
});
