<?php

use App\Models\GameState;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Route;
use App\Models\User;
use App\Models\Vendor;

function buildOrderRequestWorld(int $cashInCents = 200000): array
{
    $user = User::factory()->create();
    GameState::factory()->create([
        'user_id' => $user->id,
        'cash' => $cashInCents,
    ]);

    $vendor = Vendor::factory()->create();
    $sourceLocation = Location::factory()->create(['type' => 'vendor']);
    $targetLocation = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create(['unit_price' => 175]);

    return compact('user', 'vendor', 'sourceLocation', 'targetLocation', 'product');
}

test('store order request accepts cents payload and persists integer-cent totals', function () {
    $world = buildOrderRequestWorld(100000);

    Route::factory()->create([
        'source_id' => $world['sourceLocation']->id,
        'target_id' => $world['targetLocation']->id,
        'transport_mode' => 'Truck',
        'capacity' => 100,
        'cost' => 250,
        'is_active' => true,
    ]);

    $response = $this->actingAs($world['user'])
        ->from('/game/ordering')
        ->post('/game/orders', [
            'vendor_id' => $world['vendor']->id,
            'location_id' => $world['targetLocation']->id,
            'source_location_id' => $world['sourceLocation']->id,
            'items' => [
                [
                    'product_id' => $world['product']->id,
                    'quantity' => 2,
                    'unit_price' => '175',
                ],
            ],
        ]);

    $response->assertSessionHasNoErrors();

    $order = Order::where('user_id', $world['user']->id)->latest()->first();
    $orderItem = $order?->items()->first();
    $world['user']->gameState->refresh();

    expect($order)->not()->toBeNull()
        ->and($order->total_cost)->toBe(600)
        ->and($order->total_cost)->toBeInt();

    expect($orderItem)->not()->toBeNull()
        ->and($orderItem->cost_per_unit)->toBe(175)
        ->and($orderItem->cost_per_unit)->toBeInt();

    expect($world['user']->gameState->cash)->toBe(99400);
});

test('store order request rejects orders that exceed route capacity', function () {
    $world = buildOrderRequestWorld(100000);

    Route::factory()->create([
        'source_id' => $world['sourceLocation']->id,
        'target_id' => $world['targetLocation']->id,
        'transport_mode' => 'Truck',
        'capacity' => 5,
        'cost' => 200,
        'is_active' => true,
    ]);

    $response = $this->actingAs($world['user'])
        ->from('/game/ordering')
        ->post('/game/orders', [
            'vendor_id' => $world['vendor']->id,
            'location_id' => $world['targetLocation']->id,
            'source_location_id' => $world['sourceLocation']->id,
            'items' => [
                [
                    'product_id' => $world['product']->id,
                    'quantity' => 6,
                    'unit_price' => 100,
                ],
            ],
        ]);

    $response->assertSessionHasErrors('items');
    expect(session('errors')->first('items'))->toContain('exceeds route capacity (5)');
    expect(Order::where('user_id', $world['user']->id)->count())->toBe(0);
});

test('store order request rejects orders when funds are insufficient', function () {
    $world = buildOrderRequestWorld(500);

    Route::factory()->create([
        'source_id' => $world['sourceLocation']->id,
        'target_id' => $world['targetLocation']->id,
        'transport_mode' => 'Truck',
        'capacity' => 100,
        'cost' => 300,
        'is_active' => true,
    ]);

    $response = $this->actingAs($world['user'])
        ->from('/game/ordering')
        ->post('/game/orders', [
            'vendor_id' => $world['vendor']->id,
            'location_id' => $world['targetLocation']->id,
            'source_location_id' => $world['sourceLocation']->id,
            'items' => [
                [
                    'product_id' => $world['product']->id,
                    'quantity' => 1,
                    'unit_price' => 300,
                ],
            ],
        ]);

    $response->assertSessionHasErrors('total');
    expect(session('errors')->first('total'))
        ->toBe('Insufficient funds. Order total: $6.00, Available: $5.00.');
    expect(Order::where('user_id', $world['user']->id)->count())->toBe(0);
});

test('store order request reports no-route errors when no valid path exists', function () {
    $world = buildOrderRequestWorld(100000);

    $response = $this->actingAs($world['user'])
        ->from('/game/ordering')
        ->post('/game/orders', [
            'vendor_id' => $world['vendor']->id,
            'location_id' => $world['targetLocation']->id,
            'source_location_id' => $world['sourceLocation']->id,
            'items' => [
                [
                    'product_id' => $world['product']->id,
                    'quantity' => 1,
                    'unit_price' => 175,
                ],
            ],
        ]);

    $response->assertSessionHasErrors('location_id');
    expect(session('errors')->first('location_id'))->toBe('No valid route found to this destination.');
    expect(Order::where('user_id', $world['user']->id)->count())->toBe(0);
    expect(OrderItem::count())->toBe(0);
});
