<?php

use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\CoreGameStateSeeder;
use Database\Seeders\GraphSeeder;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

test('authenticated user can reset game', function () {
    $this->seed([
        GraphSeeder::class,
        CoreGameStateSeeder::class,
    ]);

    expect(Location::where('type', 'store')->count())->toBeGreaterThan(0);
    expect(Location::where('type', 'warehouse')->count())->toBeGreaterThan(0);
    expect(Product::count())->toBeGreaterThan(0);
    expect(Vendor::count())->toBeGreaterThan(0);

    $user = User::factory()->create();

    // Seed initial state
    $initialState = \App\Models\GameState::factory()->create(['user_id' => $user->id, 'day' => 10, 'cash' => 500]);
    $order = \App\Models\Order::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->post('/game/reset')
        ->assertRedirect('/game/dashboard'); // Expect redirect back to dashboard
    
    // Refresh user state
    $this->assertDatabaseHas('game_states', [
        'user_id' => $user->id,
        'day' => 1,
        'cash' => 1000000,
    ]);

    $this->assertDatabaseMissing('orders', [
        'id' => $order->id,
    ]);

    $gameState = \App\Models\GameState::where('user_id', $user->id)->first();
    expect($gameState->day)->toBe(1);
    expect($gameState->cash)->toBe(1000000);
});

test('guest cannot reset game', function () {
    post('/game/reset')
        ->assertRedirect('/login'); // Should redirect to login
});
