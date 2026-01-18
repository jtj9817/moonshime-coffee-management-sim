<?php

namespace Tests\Unit\Listeners;

use App\Events\OrderPlaced;
use App\Events\TransferCompleted;
use App\Listeners\DeductCash;
use App\Listeners\GenerateAlert;
use App\Listeners\UpdateInventory;
use App\Models\Alert;
use App\Models\GameState;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

use App\Listeners\UpdateMetrics;

class DAGListenersTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_metrics_listener_updates_xp_on_order()
    {
        $user = User::factory()->create();
        $gameState = GameState::factory()->create([
            'user_id' => $user->id,
            'xp' => 100,
        ]);

        $order = Order::factory()->create();

        $event = new OrderPlaced($order);
        $listener = new UpdateMetrics();

        $this->actingAs($user);

        $listener->handle($event);

        $this->assertEquals(110, $gameState->fresh()->xp); // Assuming +10 XP per order
    }

    public function test_update_inventory_listener_updates_stock_on_transfer()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $source = Location::factory()->create();
        $target = Location::factory()->create();

        // Source inventory
        Inventory::factory()->create([
            'user_id' => $user->id,
            'location_id' => $source->id,
            'product_id' => $product->id,
            'quantity' => 100,
        ]);

        // Target inventory
        $targetInventory = Inventory::factory()->create([
            'user_id' => $user->id,
            'location_id' => $target->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $transfer = Transfer::factory()->create([
            'user_id' => $user->id,
            'source_location_id' => $source->id,
            'target_location_id' => $target->id,
            'product_id' => $product->id,
            'quantity' => 50,
        ]);

        $event = new TransferCompleted($transfer);
        $listener = new UpdateInventory();

        $listener->handle($event);

        $this->assertEquals(60, $targetInventory->fresh()->quantity);
    }

    public function test_generate_alert_listener_creates_alert()
    {
        $order = Order::factory()->create([
            'total_cost' => 2000,
        ]);

        $event = new OrderPlaced($order);
        $listener = new GenerateAlert();

        $listener->handle($event);

        $this->assertDatabaseHas('alerts', [
            'type' => 'order_placed',
        ]);

        $alert = Alert::first();
        $this->assertStringContainsString('2000', $alert->message);
    }

    public function test_deduct_cash_listener_deducts_money_successfully()
    {
        $user = User::factory()->create();
        $gameState = GameState::factory()->create([
            'user_id' => $user->id,
            'cash' => 5000,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_cost' => 2000,
        ]);

        $event = new OrderPlaced($order);
        $listener = new DeductCash();

        $this->actingAs($user);

        $listener->handle($event);

        $this->assertEquals(3000, $gameState->fresh()->cash);
    }

    public function test_deduct_cash_listener_throws_exception_if_insufficient_funds()
    {
        $user = User::factory()->create();
        $gameState = GameState::factory()->create([
            'user_id' => $user->id,
            'cash' => 1000,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_cost' => 2000,
        ]);

        $event = new OrderPlaced($order);
        $listener = new DeductCash();

        $this->actingAs($user);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient funds');

        $listener->handle($event);
    }
}