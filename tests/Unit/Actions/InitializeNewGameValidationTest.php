<?php

use App\Actions\InitializeNewGame;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\Transfer;
use App\Models\User;
use Database\Seeders\CoreGameStateSeeder;
use Database\Seeders\GraphSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Tests for InitializeNewGame validation behavior.
 *
 * These tests verify that the action throws descriptive exceptions when
 * required dependencies are missing, rather than failing silently.
 */
describe('Validation Errors', function () {
    test('throws exception when no stores exist', function () {
        $user = User::factory()->create();

        expect(fn () => app(InitializeNewGame::class)->handle($user))
            ->toThrow(\RuntimeException::class, 'Cannot initialize game: No stores found');
    });

    test('throws exception when no warehouse exists', function () {
        Location::factory()->count(2)->create(['type' => 'store']);
        Product::factory()->count(2)->create();

        $user = User::factory()->create();

        expect(fn () => app(InitializeNewGame::class)->handle($user))
            ->toThrow(\RuntimeException::class, 'Cannot initialize game: No warehouse found');
    });

    test('throws exception when no products exist', function () {
        Location::factory()->count(2)->create(['type' => 'store']);
        Location::factory()->create(['type' => 'warehouse']);

        $user = User::factory()->create();

        expect(fn () => app(InitializeNewGame::class)->handle($user))
            ->toThrow(\RuntimeException::class, 'Cannot initialize game: No products found');
    });

    test('throws exception when no vendor exists for pipeline', function () {
        Location::factory()->count(2)->create(['type' => 'store']);
        Location::factory()->create(['type' => 'warehouse']);
        Product::factory()->count(2)->create();

        $user = User::factory()->create();

        expect(fn () => app(InitializeNewGame::class)->handle($user))
            ->toThrow(\RuntimeException::class, 'Cannot initialize game: No vendor found');
    });
});

describe('Idempotency', function () {
    beforeEach(function () {
        $this->seed(CoreGameStateSeeder::class);
        $this->seed(GraphSeeder::class);
    });

    test('running twice does not duplicate inventory', function () {
        $user = User::factory()->create();

        app(InitializeNewGame::class)->handle($user);
        $countAfterFirst = Inventory::where('user_id', $user->id)->count();

        app(InitializeNewGame::class)->handle($user);
        $countAfterSecond = Inventory::where('user_id', $user->id)->count();

        expect($countAfterSecond)->toBe($countAfterFirst);
        expect($countAfterFirst)->toBeGreaterThan(0);
    });

    test('running twice does not duplicate transfers', function () {
        $user = User::factory()->create();

        app(InitializeNewGame::class)->handle($user);
        $countAfterFirst = Transfer::where('user_id', $user->id)->count();

        app(InitializeNewGame::class)->handle($user);
        $countAfterSecond = Transfer::where('user_id', $user->id)->count();

        expect($countAfterSecond)->toBe($countAfterFirst);
        expect($countAfterFirst)->toBeGreaterThan(0);
    });
});
