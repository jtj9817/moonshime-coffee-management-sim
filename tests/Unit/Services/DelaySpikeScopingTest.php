<?php

use App\Models\GameState;
use App\Models\Order;
use App\Models\Product;
use App\Models\SpikeEvent;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Spikes\DelaySpike;
use App\States\Order\Pending;
use App\States\Order\Shipped;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->gameState = GameState::create([
        'user_id' => $this->user->id,
        'cash' => 10000.00,
        'xp' => 0,
        'day' => 5,
    ]);
    $this->vendor = Vendor::factory()->create();
    $this->product = Product::factory()->create();
    $this->delaySpike = new DelaySpike();
});

test('DelaySpike only affects orders owned by the spike user', function () {
    $otherUser = User::factory()->create();
    
    // Create order for spike owner
    $ownerOrder = Order::factory()->create([
        'user_id' => $this->user->id,
        'vendor_id' => $this->vendor->id,
        'delivery_day' => 7,
    ]);
    $ownerOrder->status->transitionTo(Shipped::class);
    
    // Create order for other user
    $otherOrder = Order::factory()->create([
        'user_id' => $otherUser->id,
        'vendor_id' => $this->vendor->id,
        'delivery_day' => 7,
    ]);
    $otherOrder->status->transitionTo(Shipped::class);

    $spike = SpikeEvent::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'delay',
        'magnitude' => 2, // 2 day delay
        'product_id' => null,
        'is_active' => false,
    ]);

    $this->delaySpike->apply($spike);

    $ownerOrder->refresh();
    $otherOrder->refresh();

    // Owner's order should be delayed
    expect($ownerOrder->delivery_day)->toBe(9)
        // Other user's order should NOT be affected
        ->and($otherOrder->delivery_day)->toBe(7);
});

test('DelaySpike stores original delivery data in spike meta for rollback', function () {
    $order = Order::factory()->create([
        'user_id' => $this->user->id,
        'vendor_id' => $this->vendor->id,
        'delivery_day' => 7,
        'delivery_date' => now()->addDays(2),
    ]);
    $order->status->transitionTo(Pending::class);

    $spike = SpikeEvent::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'delay',
        'magnitude' => 3,
        'product_id' => null,
        'is_active' => false,
    ]);

    $this->delaySpike->apply($spike);

    $spike->refresh();
    $meta = $spike->meta;

    expect($meta)->toHaveKey('affected_orders')
        ->and($meta['affected_orders'])->toHaveKey($order->id)
        ->and($meta['affected_orders'][$order->id]['original_delivery_day'])->toBe(7)
        ->and($meta['affected_orders'][$order->id]['original_delivery_date'])->not()->toBeNull();
});

test('DelaySpike rollback restores original delivery dates', function () {
    $order = Order::factory()->create([
        'user_id' => $this->user->id,
        'vendor_id' => $this->vendor->id,
        'delivery_day' => 6,
        'delivery_date' => now()->addDay(),
    ]);
    $order->status->transitionTo(Shipped::class);

    $originalDay = $order->delivery_day;
    $originalDate = $order->delivery_date->copy();

    $spike = SpikeEvent::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'delay',
        'magnitude' => 4,
        'product_id' => null,
        'is_active' => false,
    ]);

    // Apply delay
    $this->delaySpike->apply($spike);
    $order->refresh();

    expect($order->delivery_day)->toBe(10)
        ->and($order->delivery_date->diffInDays($originalDate, true))->toBeGreaterThan(0);

    // Rollback
    $spike->refresh();
    $this->delaySpike->rollback($spike);
    $order->refresh();

    expect($order->delivery_day)->toBe($originalDay)
        ->and($order->delivery_date->toISOString())->toBe($originalDate->toISOString());
});

test('DelaySpike respects product filtering', function () {
    $productA = Product::factory()->create();
    $productB = Product::factory()->create();

    $orderA = Order::factory()->create([
        'user_id' => $this->user->id,
        'vendor_id' => $this->vendor->id,
        'delivery_day' => 8,
    ]);
    $orderA->status->transitionTo(Pending::class);
    $orderA->items()->create([
        'product_id' => $productA->id,
        'quantity' => 10,
        'cost_per_unit' => 500,
    ]);

    $orderB = Order::factory()->create([
        'user_id' => $this->user->id,
        'vendor_id' => $this->vendor->id,
        'delivery_day' => 8,
    ]);
    $orderB->status->transitionTo(Pending::class);
    $orderB->items()->create([
        'product_id' => $productB->id,
        'quantity' => 5,
        'cost_per_unit' => 300,
    ]);

    // Spike targeting only productA
    $spike = SpikeEvent::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'delay',
        'magnitude' => 2,
        'product_id' => $productA->id,
        'is_active' => false,
    ]);

    $this->delaySpike->apply($spike);

    $orderA->refresh();
    $orderB->refresh();

    // Only orderA should be delayed
    expect($orderA->delivery_day)->toBe(10)
        ->and($orderB->delivery_day)->toBe(8);
});
