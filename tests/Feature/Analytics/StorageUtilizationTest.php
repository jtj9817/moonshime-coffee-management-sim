<?php

namespace Tests\Feature\Analytics;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class StorageUtilizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_page_provides_storage_utilization_metrics()
    {
        $user = User::factory()->create();
        
        $location1 = \App\Models\Location::factory()->create(['name' => 'Warehouse A', 'max_storage' => 1000]);
        $location2 = \App\Models\Location::factory()->create(['name' => 'Store B', 'max_storage' => 500]);
        
        $product = \App\Models\Product::factory()->create();
        
        // Loc 1: 500 units / 1000 capacity = 50%
        \App\Models\Inventory::factory()->create([
            'user_id' => $user->id,
            'location_id' => $location1->id,
            'product_id' => $product->id,
            'quantity' => 500,
        ]);
        
        // Loc 2: 400 units / 500 capacity = 80%
        \App\Models\Inventory::factory()->create([
            'user_id' => $user->id,
            'location_id' => $location2->id,
            'product_id' => $product->id,
            'quantity' => 400,
        ]);
        
        // Other user data (should be ignored)
        $otherUser = User::factory()->create();
        \App\Models\Inventory::factory()->create([
            'user_id' => $otherUser->id,
            'location_id' => $location1->id,
            'product_id' => $product->id,
            'quantity' => 100,
        ]);

        $response = $this->actingAs($user)
            ->get(route('game.analytics'));

        $response->assertStatus(200);

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('game/analytics')
            ->has('storageUtilization', 2)
            ->where('storageUtilization', function ($items) use ($location1, $location2) {
                $loc1 = collect($items)->firstWhere('location_id', $location1->id);
                $loc2 = collect($items)->firstWhere('location_id', $location2->id);
                
                return $loc1['name'] === 'Warehouse A'
                    && $loc1['capacity'] == 1000
                    && $loc1['used'] == 500
                    && $loc1['percentage'] == 50.0
                    && $loc2['name'] === 'Store B'
                    && $loc2['capacity'] == 500
                    && $loc2['used'] == 400
                    && $loc2['percentage'] == 80.0;
            })
        );
    }
}
