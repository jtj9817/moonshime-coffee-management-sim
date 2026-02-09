<?php

use App\Models\Route;
use App\Services\SpikeEventFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('factory can generate blizzard spike targeting vulnerable route', function () {
    // Create a vulnerable route
    $vulnerableRoute = Route::factory()->create(['weather_vulnerability' => true]);
    // Create a non-vulnerable route
    $safeRoute = Route::factory()->create(['weather_vulnerability' => false]);

    $factory = new SpikeEventFactory;

    // Force generation of blizzard (we might need to mock getRandomType or just loop until we get one,
    // but better to expose a way to force type or mock the protected method if possible.
    // Since getRandomType is protected, we can't mock it easily without extending.
    // Instead, let's subclass for testing or verify if we can pass type to generate (no).

    // We can use reflection to verify the logic or loop.
    // Looping is flaky.

    // Let's create a partial mock of the factory?
    // Or just modify the factory to accept a type override?
    // "generate(int $currentDay, ?string $forceType = null)"

    // I will check if I can modify generate signature.
    // But for RED phase, I'll write the test assuming I CAN force it or it happens.

    // Actually, let's modify the Factory to allow forcing type for testing.
    // Or just use Reflection.

    // I'll try to use a specific testable method if possible.
    // But failing that, I'll try to Mock the class using Mockery partial.

    $factory = Mockery::mock(SpikeEventFactory::class)->makePartial();
    $factory->shouldAllowMockingProtectedMethods();
    $factory->shouldReceive('getRandomType')->andReturn('blizzard');

    $spike = $factory->generate(1);

    expect($spike)->not->toBeNull();
    expect($spike->type)->toBe('blizzard');
    expect($spike->affected_route_id)->not->toBeNull();

    $affectedRoute = Route::find($spike->affected_route_id);
    expect($affectedRoute->weather_vulnerability)->toBeTrue();
    expect($affectedRoute->id)->toBe($vulnerableRoute->id);
});
