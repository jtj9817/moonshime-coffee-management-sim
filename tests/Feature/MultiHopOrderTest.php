<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use App\Models\Location;
use App\Models\Product;
use App\Models\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiHopOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_place_multihop_order()
    {
        // Setup
        $user = User::factory()->create();
        \App\Models\GameState::factory()->create(['user_id' => $user->id, 'cash' => 100.00]);
        $vendor = Vendor::factory()->create();
        $vendorLocation = Location::factory()->create(['type' => 'vendor', 'name' => $vendor->name . ' HQ']);
        
        // Ensure vendor has a location associated explicitly if needed, but the logic uses name matching or ID?
        // Logic uses: Location::where('id', $this->source_location_id)->first();
        // So we need to pass the ID.
        
        $hub = Location::factory()->create(['type' => 'warehouse']);
        $store = Location::factory()->create(['type' => 'store']);
        
        // Vendor -> Hub -> Store
        Route::create([
            'source_id' => $vendorLocation->id,
            'target_id' => $hub->id,
            'transport_mode' => 'truck',
            'cost' => 1.00,
            'transit_days' => 2,
            'capacity' => 1000,
            'is_active' => true
        ]);
        
        Route::create([
            'source_id' => $hub->id,
            'target_id' => $store->id,
            'transport_mode' => 'van',
            'cost' => 2.00,
            'transit_days' => 1,
            'capacity' => 500,
            'is_active' => true
        ]);

        $product = Product::factory()->create([
            'name' => 'Test Coffee',
            'category' => 'Coffee',
        ]);
        
        $vendor->products()->attach($product->id);

        $response = $this->actingAs($user)
            ->post('/game/orders', [
                'vendor_id' => $vendor->id,
                'location_id' => $store->id, // Target
                'source_location_id' => $vendorLocation->id, // Source
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 100,
                        'unit_price' => 0.10
                    ]
                ]
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        
        // Assert Order Created
        $this->assertDatabaseHas('orders', [
            'vendor_id' => $vendor->id,
            'location_id' => $store->id,
            'total_cost' => (100 * 0.10) + 1.00 + 2.00, // Items + Logistics
            // Logic: items cost + route cost.
            // Items: 100 * 0.10 = 10.00.
            // Route: 1.00 (Leg 1) + 2.00 (Leg 2) = 3.00.
            // Total: 13.00.
        ]);
        
        // Assert Shipments Created
        $order = \App\Models\Order::first();
        $this->assertCount(2, $order->shipments);
        
        $firstLeg = $order->shipments()->where('sequence_index', 0)->first();
        $this->assertEquals($vendorLocation->id, $firstLeg->source_location_id);
        $this->assertEquals($hub->id, $firstLeg->target_location_id);
        
        $secondLeg = $order->shipments()->where('sequence_index', 1)->first();
        $this->assertEquals($hub->id, $secondLeg->source_location_id);
        $this->assertEquals($store->id, $secondLeg->target_location_id);
    }
}
