<?php

namespace Tests\Feature\Analytics\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Location;
use App\Models\Product;

class InventoryHistoryMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_history_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('inventory_history'));
    }

    public function test_inventory_history_has_composite_unique_constraint(): void
    {
        // Setup data
        $user = User::factory()->create();
        $location = Location::factory()->create();
        $product = Product::factory()->create();
        $day = 1;
        $quantity = 10;

        // Insert first record
        DB::table('inventory_history')->insert([
            'user_id' => $user->id,
            'location_id' => $location->id,
            'product_id' => $product->id,
            'day' => $day,
            'quantity' => $quantity,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempt duplicate insert
        try {
            DB::table('inventory_history')->insert([
                'user_id' => $user->id,
                'location_id' => $location->id,
                'product_id' => $product->id,
                'day' => $day,
                'quantity' => 20,
                 'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Should have thrown QueryException for duplicate entry');
        } catch (QueryException $e) {
            // Postgres error 23505 is unique_violation
            $this->assertEquals('23505', $e->getCode(), 'Expected unique violation error code 23505');
        }
    }
}
