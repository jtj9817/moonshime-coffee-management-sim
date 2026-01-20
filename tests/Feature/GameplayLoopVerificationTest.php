<?php

use App\Models\GameState;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Route;
use App\Models\Shipment;
use App\Models\SpikeEvent;
use App\Models\Vendor;
use App\Models\User;
use App\Models\Inventory;
use App\Services\SimulationService;
use App\States\Order\Draft;
use App\States\Order\Pending;
use App\States\Order\Shipped;
use App\States\Order\Delivered;
use App\Events\OrderPlaced;
use App\Events\SpikeOccurred;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('comprehensive 5-day gameplay loop simulation with player agency', function () {
    // --- 1. WORLD SETUP ---
    $user = User::factory()->create();
    $gameState = GameState::factory()->create([
        'user_id' => $user->id,
        'day' => 1,
        'cash' => 1000000 // Start with $1M
    ]);
    Auth::login($user);

    $vendor = Vendor::factory()->create(['name' => 'Global Beans']);
    $vendorLoc = Location::factory()->create(['type' => 'vendor', 'name' => 'Global Beans HQ']);
    $warehouse = Location::factory()->create(['type' => 'warehouse', 'name' => 'Central Warehouse']);
    $product = Product::factory()->create([
        'name' => 'Premium Arabica',
        'storage_cost' => 10,
        'is_perishable' => false
    ]);
    
    // Connect Vendor to Warehouse (Standard Route)
    $standardRoute = Route::factory()->create([
        'source_id' => $vendorLoc->id,
        'target_id' => $warehouse->id,
        'transport_mode' => 'Truck',
        'cost' => 1000,
        'transit_days' => 2,
        'is_active' => true,
        'weather_vulnerability' => true,
    ]);

    // Connect Vendor to Warehouse (Premium Route - Air)
    $premiumRoute = Route::factory()->create([
        'source_id' => $vendorLoc->id,
        'target_id' => $warehouse->id,
        'transport_mode' => 'Air',
        'cost' => 5000,
        'transit_days' => 1,
        'is_active' => true,
        'weather_vulnerability' => false,
    ]);

    $service = new SimulationService($gameState);

    // --- DAY 1: Initial Order (Player Agency) ---
    expect($gameState->cash)->toBe(1000000);

    // Player decides to place an order via the standard route
    $order = Order::create([
        'user_id' => $user->id,
        'vendor_id' => $vendor->id,
        'location_id' => $warehouse->id,
        'total_cost' => 10000, 
        'status' => 'draft',
    ]);    
    Shipment::create([
        'order_id' => $order->id,
        'route_id' => $standardRoute->id,
        'source_location_id' => $standardRoute->source_id,
        'target_location_id' => $standardRoute->target_id,
        'status' => 'pending',
        'sequence_index' => 0,
    ]);
    
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 100,
        'cost_per_unit' => 90,
    ]);

    // Finalize order (Trigger State Transition and Event)
    $order->status->transitionTo(Pending::class);
    event(new OrderPlaced($order));

    // Verify Cash Deduction
    expect($gameState->fresh()->cash)->toBe(990000);

    // Advance to Day 2
    $service->advanceTime();
    expect($gameState->fresh()->day)->toBe(2);

    // --- DAY 2: Disruption (Blizzard) ---
    $blizzard = SpikeEvent::create([
        'user_id' => $user->id,
        'type' => 'blizzard',
        'magnitude' => 1.0,
        'duration' => 2,
        'affected_route_id' => $standardRoute->id,
        'starts_at_day' => 2,
        'ends_at_day' => 4,
        'is_active' => false,
    ]);        
    
    $blizzard->update(['is_active' => true]);
    event(new SpikeOccurred($blizzard));
    
    // Verify standard route is blocked
    expect($standardRoute->fresh()->is_active)->toBeFalse();

    // Player agency: Responding to disruption
    $logistics = app(\App\Services\LogisticsService::class);
    $bestPath = $logistics->findBestRoute($vendorLoc, $warehouse);
    
    // Verify the engine suggests the Air route because Truck is blocked
    expect($bestPath->first()->id)->toBe($premiumRoute->id);

    // Player places an emergency order via Air
    $emergencyOrder = Order::create([
        'user_id' => $user->id,
        'vendor_id' => $vendor->id,
        'location_id' => $warehouse->id,
        'total_cost' => 20000,
        'status' => 'draft',
    ]);
    Shipment::create([
        'order_id' => $emergencyOrder->id,
        'route_id' => $premiumRoute->id,
        'source_location_id' => $premiumRoute->source_id,
        'target_location_id' => $premiumRoute->target_id,
        'status' => 'pending',
        'sequence_index' => 0,
    ]);
    
    $emergencyOrder->status->transitionTo(Pending::class);
    OrderItem::create([
        'order_id' => $emergencyOrder->id,
        'product_id' => $product->id,
        'quantity' => 50,
        'cost_per_unit' => 400,
    ]);
    event(new OrderPlaced($emergencyOrder));

    expect($gameState->fresh()->cash)->toBe(970000);

    // Advance to Day 3
    $service->advanceTime();
    expect($gameState->fresh()->day)->toBe(3);

    // --- DAY 3: Progression ---
    // Ship the standard order (This should set delivery_day to 3 + 2 = 5)
    $order->status->transitionTo(Shipped::class);
    // emergencyOrder transit_days is 1. Day 3 + 1 = 4.
    $emergencyOrder->status->transitionTo(Shipped::class);
    
    // --- DAY 4: Restoration & Delivery ---
    $service->advanceTime(); // Advance to Day 4
    expect($gameState->fresh()->day)->toBe(4);

    // Blizzard should be ended by SimulationService::processEventTick
    expect($blizzard->fresh()->is_active)->toBeFalse();
    expect($standardRoute->fresh()->is_active)->toBeTrue('Route should be restored');

    // Emergency Order should be Delivered (Shipped on Day 3, transit 1 -> Day 4)
    expect($emergencyOrder->fresh()->status)->toBeInstanceOf(Delivered::class);
    
    // Standard Order should NOT be delivered yet (Shipped on Day 3, transit 2 -> Day 5)
    expect($order->fresh()->status)->toBeInstanceOf(Shipped::class);

    // --- DAY 5: Final Verification ---
    $service->advanceTime();
    expect($gameState->fresh()->day)->toBe(5);

    // Now standard order should be delivered
    expect($order->fresh()->status)->toBeInstanceOf(Delivered::class);

    // VERIFY INVENTORY
    $inventory = Inventory::where('location_id', $warehouse->id)
        ->where('product_id', $product->id)
        ->where('user_id', $user->id)
        ->first();
    
    expect($inventory)->not->toBeNull('Inventory record should be created');
    expect($inventory->quantity)->toBe(150, 'Inventory quantity should match both orders (100+50)');

    // --- FINAL VERIFICATION ---
    // Total cash: 1,000,000 - 30,000 - 500 - 1500 = 968,000
    expect($gameState->fresh()->cash)->toBe(968000);

    // --- CANCELLATION & REFUNDS ---
    // 1. Cannot cancel Delivered order
    try {
        $order->fresh()->status->transitionTo(\App\States\Order\Cancelled::class);
        $this->fail("Should not be able to cancel delivered order");
    } catch (\Throwable $e) {
        // Expected
    }

    // 2. Cancel a new Shipped order for refund
    $shippedOrder = Order::create([
        'user_id' => $user->id,
        'vendor_id' => $vendor->id,
        'location_id' => $warehouse->id,
        'total_cost' => 5000,
        'status' => 'draft',
    ]);
    Shipment::create([
        'order_id' => $shippedOrder->id,
        'route_id' => $standardRoute->id,
        'source_location_id' => $standardRoute->source_id,
        'target_location_id' => $standardRoute->target_id,
        'status' => 'pending',
        'sequence_index' => 0,
    ]);
    $shippedOrder->status->transitionTo(Pending::class);
    event(new OrderPlaced($shippedOrder));
    expect($gameState->fresh()->cash)->toBe(963000); // 968k - 5k

    $shippedOrder->status->transitionTo(Shipped::class);
    $shippedOrder->status->transitionTo(\App\States\Order\Cancelled::class);

    // Verify refund (963k + 5k = 968k)
    expect($gameState->fresh()->cash)->toBe(968000);
    expect($shippedOrder->fresh()->status)->toBeInstanceOf(\App\States\Order\Cancelled::class);

    // --- ROUTE CAPACITY ---
    $massiveOrder = Order::create([
        'user_id' => $user->id,
        'vendor_id' => $vendor->id,
        'location_id' => $warehouse->id,
        'total_cost' => 1000,
        'status' => 'draft',
    ]);
    Shipment::create([
        'order_id' => $massiveOrder->id,
        'route_id' => $standardRoute->id,
        'source_location_id' => $standardRoute->source_id,
        'target_location_id' => $standardRoute->target_id,
        'status' => 'pending',
        'sequence_index' => 0,
    ]);
    OrderItem::create([
        'order_id' => $massiveOrder->id,
        'product_id' => $product->id,
        'quantity' => $standardRoute->capacity + 1,
        'cost_per_unit' => 1,
    ]);

    try {
        $massiveOrder->status->transitionTo(Pending::class);
        $massiveOrder->status->transitionTo(Shipped::class);
        $this->fail("Should not be able to ship order exceeding route capacity");
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toContain('exceeds route capacity');
    }
});
