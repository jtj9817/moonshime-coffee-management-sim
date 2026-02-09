<?php

namespace Tests\Feature\Analytics\Listeners;

use App\Events\TimeAdvanced;
use App\Listeners\SnapshotInventoryLevels;
use App\Models\GameState;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SnapshotInventoryLevelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_listener_is_attached_to_event(): void
    {
        Event::fake();
        Event::assertListening(
            TimeAdvanced::class,
            SnapshotInventoryLevels::class
        );
    }

    public function test_it_snapshots_inventory_on_time_advanced(): void
    {
        // Setup
        $user = User::factory()->create();
        $location = Location::factory()->create();
        $product = Product::factory()->create();
        $gameState = GameState::factory()->create(['user_id' => $user->id]);

        // Create initial inventory
        Inventory::factory()->create([
            'user_id' => $user->id,
            'location_id' => $location->id,
            'product_id' => $product->id,
            'quantity' => 50,
        ]);

        $day = 5;

        $event = new TimeAdvanced($day, $gameState);
        $listener = new SnapshotInventoryLevels;
        $listener->handle($event);

        // Assert
        $this->assertDatabaseHas('inventory_history', [
            'user_id' => $user->id,
            'location_id' => $location->id,
            'product_id' => $product->id,
            'day' => $day,
            'quantity' => 50,
        ]);
    }

    public function test_it_handles_duplicate_runs_gracefully(): void
    {
        $user = User::factory()->create();
        $location = Location::factory()->create();
        $product = Product::factory()->create();
        $gameState = GameState::factory()->create(['user_id' => $user->id]);

        Inventory::factory()->create([
            'user_id' => $user->id,
            'location_id' => $location->id,
            'product_id' => $product->id,
            'quantity' => 50,
        ]);

        $day = 5;
        $event = new TimeAdvanced($day, $gameState);
        $listener = new SnapshotInventoryLevels;

        // First run
        $listener->handle($event);

        // Second run
        $listener->handle($event);

        $this->assertDatabaseCount('inventory_history', 1);
    }
}
