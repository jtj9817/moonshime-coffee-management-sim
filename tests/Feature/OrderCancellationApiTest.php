<?php

use App\Models\GameState;
use App\Models\Order;
use App\Models\User;
use App\States\Order\Cancelled;
use App\States\Order\Delivered;
use App\States\Order\Shipped;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

test('authenticated users can cancel shipped orders', function () {
    $user = User::factory()->create();
    $gameState = GameState::factory()->create(['user_id' => $user->id, 'cash' => 10.00]);
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => Shipped::class,
        'total_cost' => 5.00,
    ]);

    actingAs($user)
        ->postJson("/game/orders/{$order->id}/cancel")
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Order cancelled successfully.',
        ]);

    expect($order->fresh()->status)->toBeInstanceOf(Cancelled::class);
    expect($gameState->fresh()->cash)->toBe(15.00); // 10.00 + 5.00 refund
});

test('users cannot cancel delivered orders', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => Delivered::class,
    ]);

    actingAs($user)
        ->postJson("/game/orders/{$order->id}/cancel")
        ->assertStatus(422) // or 403
        ->assertJson(['success' => false]);

    expect($order->fresh()->status)->toBeInstanceOf(Delivered::class);
});

test('users cannot cancel orders belonging to others', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $otherUser->id,
        'status' => Shipped::class,
    ]);

    actingAs($user)
        ->postJson("/game/orders/{$order->id}/cancel")
        ->assertStatus(403);
});
