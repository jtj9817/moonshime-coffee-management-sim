<?php

namespace Tests\Feature\Analytics;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AnalyticsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_page_displays_inventory_trends_from_history()
    {
        $user = User::factory()->create();
        \App\Models\GameState::factory()->create(['user_id' => $user->id]);
        $location1 = \App\Models\Location::factory()->create();
        $location2 = \App\Models\Location::factory()->create();
        $product1 = \App\Models\Product::factory()->create();
        $product2 = \App\Models\Product::factory()->create();
        
        // Seed inventory history
        // Day 1: 30 units total
        DB::table('inventory_history')->insert([
            ['user_id' => $user->id, 'location_id' => $location1->id, 'product_id' => $product1->id, 'day' => 1, 'quantity' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $user->id, 'location_id' => $location2->id, 'product_id' => $product2->id, 'day' => 1, 'quantity' => 20, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Day 2: 40 units total
        DB::table('inventory_history')->insert([
            ['user_id' => $user->id, 'location_id' => $location1->id, 'product_id' => $product1->id, 'day' => 2, 'quantity' => 15, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $user->id, 'location_id' => $location2->id, 'product_id' => $product2->id, 'day' => 2, 'quantity' => 25, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Another user's data (should be ignored)
        $otherUser = User::factory()->create();
        \App\Models\GameState::factory()->create(['user_id' => $otherUser->id]);
        DB::table('inventory_history')->insert([
            ['user_id' => $otherUser->id, 'location_id' => $location1->id, 'product_id' => $product1->id, 'day' => 1, 'quantity' => 500, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($user)
            ->get(route('game.analytics'));

        $response->assertStatus(200);

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('game/analytics')
            ->has('inventoryTrends')
            ->where('inventoryTrends.0.day', 1)
            ->where('inventoryTrends.0.value', 30) // 10 + 20
            ->where('inventoryTrends.1.day', 2)
            ->where('inventoryTrends.1.value', 40) // 15 + 25
        );
    }

    public function test_analytics_page_displays_spending_by_category()
    {
        $user = User::factory()->create();
        \App\Models\GameState::factory()->create(['user_id' => $user->id]);
        
        $beans = \App\Models\Product::factory()->create(['category' => 'Beans', 'name' => 'Beans A']);
        $milk = \App\Models\Product::factory()->create(['category' => 'Milk', 'name' => 'Milk B']);
        
        $order1 = \App\Models\Order::factory()->create(['user_id' => $user->id]);
        \App\Models\OrderItem::factory()->create([
            'order_id' => $order1->id,
            'product_id' => $beans->id,
            'quantity' => 10,
            'cost_per_unit' => 10, // 100
        ]);
        \App\Models\OrderItem::factory()->create([
            'order_id' => $order1->id,
            'product_id' => $milk->id,
            'quantity' => 20,
            'cost_per_unit' => 5, // 100
        ]);

        $order2 = \App\Models\Order::factory()->create(['user_id' => $user->id]);
        \App\Models\OrderItem::factory()->create([
            'order_id' => $order2->id,
            'product_id' => $beans->id,
            'quantity' => 5,
            'cost_per_unit' => 10, // 50
        ]);
        
        // Another user
        $otherUser = User::factory()->create();
        \App\Models\GameState::factory()->create(['user_id' => $otherUser->id]);
        $otherOrder = \App\Models\Order::factory()->create(['user_id' => $otherUser->id]);
        \App\Models\OrderItem::factory()->create([
            'order_id' => $otherOrder->id,
            'product_id' => $beans->id,
            'quantity' => 100,
            'cost_per_unit' => 10,
        ]);

        $response = $this->actingAs($user)
            ->get(route('game.analytics'));

        $response->assertStatus(200);
        
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('game/analytics')
            ->has('spendingByCategory', 2)
            ->where('spendingByCategory', function ($items) {
                $beans = collect($items)->firstWhere('category', 'Beans');
                $milk = collect($items)->firstWhere('category', 'Milk');
                
                return $beans['amount'] == 150 && $milk['amount'] == 100;
            })
        );
    }

    public function test_analytics_page_displays_enhanced_location_comparison()
    {
        $user = User::factory()->create();
        $gameState = \App\Models\GameState::factory()->create(['user_id' => $user->id, 'cash' => 1000]);
        
        $location = \App\Models\Location::factory()->create(['name' => 'Central', 'max_storage' => 100]);
        $productA = \App\Models\Product::factory()->create(['unit_price' => 10]);
        $productB = \App\Models\Product::factory()->create(['unit_price' => 20]);
        
        // User 1 Inventory: 50 units * $10 = $500. Utilization 50/100 = 50%. Item Count = 1.
        \App\Models\Inventory::factory()->create([
            'user_id' => $user->id,
            'location_id' => $location->id,
            'product_id' => $productA->id,
            'quantity' => 50,
        ]);
        
        // Other User Inventory: 20 units. Should be ignored.
        // Must use different product to avoid unique(location_id, product_id) if user_id is not in unique
        $otherUser = User::factory()->create();
        \App\Models\GameState::factory()->create(['user_id' => $otherUser->id]);
        \App\Models\Inventory::factory()->create([
            'user_id' => $otherUser->id,
            'location_id' => $location->id,
            'product_id' => $productB->id,
            'quantity' => 20,
        ]);

        $response = $this->actingAs($user)
            ->get(route('game.analytics'));

        $response->assertStatus(200);
        
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('game/analytics')
            ->has('locationComparison')
            ->where('locationComparison', function ($locations) {
                $target = collect($locations)->firstWhere('name', 'Central');
                
                if (!$target) return false;

                return $target['inventoryValue'] == 500
                    && $target['utilization'] == 50
                    && $target['itemCount'] == 1;
            })
            ->has('overviewMetrics')
            ->where('overviewMetrics.cash', 1000)
            ->where('overviewMetrics.netWorth', 1500) // 1000 cash + 500 inventory
        );
    }
}
