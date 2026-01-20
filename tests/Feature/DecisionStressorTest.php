<?php

use App\Models\GameState;
use App\Models\Location;
use App\Models\Route;
use App\Models\SpikeEvent;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\Vendor;
use App\Services\SimulationService;
use App\Services\LogisticsService;
use App\Events\SpikeOccurred;
use App\Events\OrderPlaced;
use App\States\Order\Pending;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('scenario b: "the decision stressor" stress test', function () {
    // --- 1. SETUP GRAPH WITH MULTIPLE PATHS ---
    $user = User::factory()->create();
    \Illuminate\Support\Facades\Auth::login($user);
    $gameState = GameState::factory()->create([
        'user_id' => $user->id,
        'day' => 1,
        'cash' => 1000.00
    ]);
    
    $vendorLoc = Location::factory()->create(['type' => 'vendor', 'name' => 'Supplier Hub']);
    $warehouse = Location::factory()->create(['type' => 'warehouse', 'name' => 'Main Warehouse']);
    $product = Product::factory()->create(['name' => 'Raw Beans', 'storage_cost' => 0.05]);

    // Path 1: Cheap but vulnerable (Truck)
    $cheapRoute = Route::factory()->create([
        'source_id' => $vendorLoc->id,
        'target_id' => $warehouse->id,
        'transport_mode' => 'Truck',
        'cost' => 5.00,
        'transit_days' => 3,
        'is_active' => true,
        'weather_vulnerability' => true,
    ]);

    // Path 2: Expensive but stable (Air)
    $premiumRoute = Route::factory()->create([
        'source_id' => $vendorLoc->id,
        'target_id' => $warehouse->id,
        'transport_mode' => 'Air',
        'cost' => 25.00,
        'transit_days' => 1,
        'is_active' => true,
        'weather_vulnerability' => false,
    ]);

    $logistics = app(LogisticsService::class);

    // --- 2. APPLY CONCURRENT SPIKES ---
    // A. Price Spike on Raw Beans
    $priceSpike = SpikeEvent::create([
        'user_id' => $user->id,
        'type' => 'price',
        'product_id' => $product->id,
        'magnitude' => 0.5, // 50% price increase
        'duration' => 3,
        'starts_at_day' => 1,
        'ends_at_day' => 4,
        'is_active' => true,
    ]);
    
    // B. Blizzard on the Cheap Route
    $blizzard = SpikeEvent::create([
        'user_id' => $user->id,
        'type' => 'blizzard',
        'affected_route_id' => $cheapRoute->id,
        'magnitude' => 1.0,
        'duration' => 3,
        'starts_at_day' => 1,
        'ends_at_day' => 4,
        'is_active' => true,
    ]);
    event(new SpikeOccurred($blizzard)); // Actually deactivates the route

    // --- 3. VERIFY PATHFINDING UNDER STRESS ---
    expect($cheapRoute->fresh()->is_active)->toBeFalse();
    
    $bestPath = $logistics->findBestRoute($vendorLoc, $warehouse);
    expect($bestPath->count())->toBe(1);
    expect($bestPath->first()->id)->toBe($premiumRoute->id);
    expect($logistics->isPremiumRoute($bestPath->first()))->toBeTrue();

    // --- 4. VERIFY PLAYER DECISION PERSISTENCE ---
    $vendor = Vendor::factory()->create();
    
    // Player places an order via the premium route due to breakdown
    $order = Order::create([
        'user_id' => $user->id,
        'vendor_id' => $vendor->id,
        'location_id' => $warehouse->id,
        'total_cost' => 100.00, 
        'status' => 'draft',
    ]);
    
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 100,
        'cost_per_unit' => 1.00,
    ]);
    Shipment::create([
        'order_id' => $order->id,
        'route_id' => $premiumRoute->id,
        'source_location_id' => $premiumRoute->source_id,
        'target_location_id' => $premiumRoute->target_id,
        'status' => 'pending',
        'sequence_index' => 0,
    ]);

    $order->status->transitionTo(Pending::class);
    event(new OrderPlaced($order));

    // Verify order is tied to premium route
    expect($order->shipments()->value('route_id'))->toBe($premiumRoute->id);
    
    // Verify cash impact ($1,000.00 - $100.00 = $900.00)
    expect($gameState->fresh()->cash)->toBe(900.00);
});
