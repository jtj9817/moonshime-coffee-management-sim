<?php

use App\Models\Alert;
use App\Models\GameState;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Route;
use App\Models\SpikeEvent;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Helper: create a fully-initialized user with game state, orders, inventory, etc.
 */
function createUserWithGameData(string $name, int $cash = 500000): array
{
    $user = User::factory()->create(['name' => $name]);
    $gameState = GameState::factory()->create([
        'user_id' => $user->id,
        'day' => 5,
        'cash' => $cash,
    ]);

    $location = Location::factory()->create(['name' => "{$name} Warehouse"]);
    $product = Product::factory()->create(['name' => "{$name} Beans"]);
    $vendor = Vendor::factory()->create(['name' => "{$name} Vendor"]);

    $inventory = Inventory::create([
        'user_id' => $user->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 100,
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'vendor_id' => $vendor->id,
        'location_id' => $location->id,
        'total_cost' => 5000,
        'status' => 'pending',
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 50,
        'cost_per_unit' => 100,
    ]);

    $transfer = Transfer::create([
        'user_id' => $user->id,
        'source_location_id' => $location->id,
        'target_location_id' => Location::factory()->create()->id,
        'product_id' => $product->id,
        'quantity' => 10,
    ]);

    $alert = Alert::create([
        'user_id' => $user->id,
        'type' => 'order_placed',
        'severity' => 'info',
        'message' => "{$name}'s alert",
        'is_read' => false,
    ]);

    $spike = SpikeEvent::create([
        'user_id' => $user->id,
        'type' => 'demand',
        'magnitude' => 1.5,
        'duration' => 3,
        'starts_at_day' => 4,
        'ends_at_day' => 7,
        'is_active' => true,
    ]);

    return compact('user', 'gameState', 'location', 'product', 'vendor', 'inventory', 'order', 'transfer', 'alert', 'spike');
}

// --- INVENTORY PAGE ISOLATION ---
test('inventory page only shows authenticated user inventory', function () {
    $alice = createUserWithGameData('Alice');
    $bob = createUserWithGameData('Bob');

    $response = $this->actingAs($alice['user'])->get(route('game.inventory'));

    $response->assertOk();
    $inventoryProp = $response->original->getData()['page']['props']['inventory'];

    // All inventory items must belong to Alice
    $inventoryUserIds = collect($inventoryProp)->pluck('user_id')->unique()->values()->all();
    expect($inventoryUserIds)->toBe([$alice['user']->id]);
});

// --- ORDERING PAGE ISOLATION ---
test('ordering page only shows authenticated user orders', function () {
    $alice = createUserWithGameData('Alice');
    $bob = createUserWithGameData('Bob');

    $response = $this->actingAs($alice['user'])->get(route('game.ordering'));

    $response->assertOk();
    $ordersProp = $response->original->getData()['page']['props']['orders'];

    $orderUserIds = collect($ordersProp)->pluck('user_id')->unique()->values()->all();
    expect($orderUserIds)->toBe([$alice['user']->id]);
});

// --- TRANSFERS PAGE ISOLATION ---
test('transfers page only shows authenticated user transfers', function () {
    $alice = createUserWithGameData('Alice');
    $bob = createUserWithGameData('Bob');

    $response = $this->actingAs($alice['user'])->get(route('game.transfers'));

    $response->assertOk();
    $transfersProp = $response->original->getData()['page']['props']['transfers'];

    $transferUserIds = collect($transfersProp)->pluck('user_id')->unique()->values()->all();
    expect($transferUserIds)->toBe([$alice['user']->id]);
});

// --- SKU DETAIL ISOLATION ---
test('sku detail page does not leak other user inventory', function () {
    // Create shared location and product
    $sharedLocation = Location::factory()->create(['name' => 'Shared Location']);
    $sharedProduct = Product::factory()->create(['name' => 'Shared Beans']);

    $alice = User::factory()->create(['name' => 'Alice']);
    GameState::factory()->create(['user_id' => $alice->id, 'day' => 5, 'cash' => 500000]);
    $bob = User::factory()->create(['name' => 'Bob']);

    // Only Bob has inventory at this location — Alice has none
    Inventory::create([
        'user_id' => $bob->id,
        'location_id' => $sharedLocation->id,
        'product_id' => $sharedProduct->id,
        'quantity' => 999,
    ]);

    $response = $this->actingAs($alice)
        ->get(route('game.sku-detail', [$sharedLocation, $sharedProduct]));

    $response->assertOk();
    $inventoryProp = $response->original->getData()['page']['props']['inventory'];

    // Alice has no inventory here — should be null, not Bob's record
    expect($inventoryProp)->toBeNull();
});

// --- VENDOR PAGE ISOLATION (order counts) ---
test('vendor page order counts only include authenticated user orders', function () {
    $alice = createUserWithGameData('Alice');
    $bob = createUserWithGameData('Bob');

    // Both users order from the same vendor
    $sharedVendor = Vendor::factory()->create(['name' => 'Shared Vendor']);
    Order::create([
        'user_id' => $alice['user']->id,
        'vendor_id' => $sharedVendor->id,
        'location_id' => $alice['location']->id,
        'total_cost' => 1000,
        'status' => 'pending',
    ]);
    Order::create([
        'user_id' => $bob['user']->id,
        'vendor_id' => $sharedVendor->id,
        'location_id' => $bob['location']->id,
        'total_cost' => 2000,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($alice['user'])->get(route('game.vendors'));

    $response->assertOk();
    $vendorsProp = $response->original->getData()['page']['props']['vendors'];

    $sharedVendorData = collect($vendorsProp)->firstWhere('id', $sharedVendor->id);
    // Should only count Alice's order (1), not Bob's
    expect($sharedVendorData['orders_count'])->toBe(1);
});

// --- VENDOR DETAIL ISOLATION ---
test('vendor detail page only shows authenticated user orders', function () {
    $alice = createUserWithGameData('Alice');
    $bob = createUserWithGameData('Bob');

    $sharedVendor = Vendor::factory()->create(['name' => 'Shared Vendor']);
    Order::create([
        'user_id' => $alice['user']->id,
        'vendor_id' => $sharedVendor->id,
        'location_id' => $alice['location']->id,
        'total_cost' => 1000,
        'status' => 'pending',
    ]);
    Order::create([
        'user_id' => $bob['user']->id,
        'vendor_id' => $sharedVendor->id,
        'location_id' => $bob['location']->id,
        'total_cost' => 2000,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($alice['user'])
        ->get(route('game.vendor-detail', $sharedVendor));

    $response->assertOk();
    $vendorProp = $response->original->getData()['page']['props']['vendor'];
    $metricsProp = $response->original->getData()['page']['props']['metrics'];

    // Orders loaded on vendor should only be Alice's
    $orderUserIds = collect($vendorProp['orders'])->pluck('user_id')->unique()->values()->all();
    expect($orderUserIds)->toBe([$alice['user']->id]);

    // Metrics should only reflect Alice's orders
    expect($metricsProp['totalOrders'])->toBe(1);
    expect($metricsProp['totalSpent'])->toBe(1000.0);
});

// --- ALERT AUTHORIZATION ---
test('user cannot mark another user alert as read', function () {
    $alice = createUserWithGameData('Alice');
    $bob = createUserWithGameData('Bob');

    $response = $this->actingAs($alice['user'])
        ->post(route('game.alerts.read', $bob['alert']));

    // Should be forbidden (403) or redirect with error, not 200
    expect($response->status())->toBeGreaterThanOrEqual(403);
});

// --- LOGISTICS ROUTES SPIKE ISOLATION ---
test('logistics routes only show authenticated user spike effects', function () {
    $alice = createUserWithGameData('Alice');
    $bob = createUserWithGameData('Bob');

    $source = Location::factory()->create();
    $target = Location::factory()->create();
    $route = Route::factory()->create([
        'source_id' => $source->id,
        'target_id' => $target->id,
        'is_active' => true,
        'cost' => 100,
    ]);

    // Bob has a spike on this route, Alice does not
    SpikeEvent::create([
        'user_id' => $bob['user']->id,
        'type' => 'blizzard',
        'affected_route_id' => $route->id,
        'magnitude' => 2.0,
        'duration' => 3,
        'starts_at_day' => 1,
        'ends_at_day' => 4,
        'is_active' => true,
    ]);

    $response = $this->actingAs($alice['user'])
        ->getJson(route('game.logistics.routes', ['source_id' => $source->id]));

    $response->assertOk();
    $routeData = collect($response->json('data'))->firstWhere('id', $route->id);

    // Alice should NOT see Bob's spike as a blocked_reason
    expect($routeData['blocked_reason'])->toBeNull();
    // Cost should be base cost (100), not spike-inflated
    expect($routeData['cost'])->toBe(100);
});
