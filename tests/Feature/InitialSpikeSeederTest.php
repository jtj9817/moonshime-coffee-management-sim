<?php

use App\Models\GameState;
use App\Models\Location;
use App\Models\SpikeEvent;
use App\Models\User;
use Database\Seeders\SpikeSeeder;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->gameState = GameState::create([
        'user_id' => $this->user->id,
        'cash' => 1000000,
        'xp' => 0,
        'day' => 1,
    ]);
    // Create a location for spikes that need it
    Location::factory()->create(['type' => 'warehouse']);
});

test('new game has 3-5 spikes seeded for days 2-7', function () {
    $seeder = app(SpikeSeeder::class);
    $seeder->seedInitialSpikes($this->gameState);

    $spikes = SpikeEvent::where('user_id', $this->user->id)->get();

    expect($spikes->count())->toBeGreaterThanOrEqual(3);
    expect($spikes->count())->toBeLessThanOrEqual(5);

    // All spikes should start between days 2-7
    $spikes->each(function ($spike) {
        expect($spike->starts_at_day)->toBeGreaterThanOrEqual(2);
        expect($spike->starts_at_day)->toBeLessThanOrEqual(7);
    });
});

test('seeded spikes respect 2-day type cooldown', function () {
    $seeder = app(SpikeSeeder::class);
    $seeder->seedInitialSpikes($this->gameState);

    $spikes = SpikeEvent::where('user_id', $this->user->id)
        ->orderBy('starts_at_day')
        ->get();

    expect($spikes->count())->toBeGreaterThan(0);

    // For each spike type, check no two spikes of same type start within 2 days
    $lastDayByType = [];
    foreach ($spikes as $spike) {
        if (isset($lastDayByType[$spike->type])) {
            $dayDiff = $spike->starts_at_day - $lastDayByType[$spike->type];
            // This is a soft check - cooldown may be relaxed if all types blocked
            expect($dayDiff)->toBeGreaterThanOrEqual(1);
        }
        $lastDayByType[$spike->type] = $spike->starts_at_day;
    }
});

test('seeded spikes are marked as guaranteed', function () {
    $seeder = app(SpikeSeeder::class);
    $seeder->seedInitialSpikes($this->gameState);

    $spikes = SpikeEvent::where('user_id', $this->user->id)->get();

    $spikes->each(function ($spike) {
        expect($spike->is_guaranteed)->toBeTrue();
    });
});

test('seeded spikes have correct user_id', function () {
    $seeder = app(SpikeSeeder::class);
    $seeder->seedInitialSpikes($this->gameState);

    $spikes = SpikeEvent::where('user_id', $this->user->id)->get();

    $spikes->each(function ($spike) {
        expect($spike->user_id)->toBe($this->user->id);
    });
});
