<?php

namespace Tests\Unit;

use App\Models\Inventory;
use App\Services\InventoryManagementService;
use App\Services\Strategies\RestockStrategyInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class InventoryManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_restock_uses_strategy_when_quantity_not_provided()
    {
        // Mock Strategy
        $strategy = Mockery::mock(RestockStrategyInterface::class);
        $strategy->shouldReceive('calculateReorderQuantity')->once()->andReturn(50);
        
        // Create Service
        $service = new InventoryManagementService($strategy);
        
        // Create Real Inventory
        $inventory = Inventory::factory()->create(['quantity' => 10]);
        
        // Act
        $added = $service->restock($inventory);
        
        // Assert
        $this->assertEquals(50, $added);
        $this->assertEquals(60, $inventory->fresh()->quantity);
    }

    public function test_restock_uses_explicit_quantity_if_provided()
    {
        $strategy = Mockery::mock(RestockStrategyInterface::class);
        $strategy->shouldNotReceive('calculateReorderQuantity');
        
        $service = new InventoryManagementService($strategy);
        $inventory = Inventory::factory()->create(['quantity' => 10]);
        
        $added = $service->restock($inventory, 25);
        
        $this->assertEquals(25, $added);
        $this->assertEquals(35, $inventory->fresh()->quantity);
    }

    public function test_consume_decrements_stock()
    {
        $strategy = Mockery::mock(RestockStrategyInterface::class);
        $service = new InventoryManagementService($strategy);
        
        $inventory = Inventory::factory()->create(['quantity' => 10]);
        
        $service->consume($inventory, 4);
        
        $this->assertEquals(6, $inventory->fresh()->quantity);
    }

    public function test_consume_throws_exception_if_insufficient_stock()
    {
        $strategy = Mockery::mock(RestockStrategyInterface::class);
        $service = new InventoryManagementService($strategy);
        
        $inventory = Inventory::factory()->create(['quantity' => 5]);
        
        $this->expectException(InvalidArgumentException::class);
        
        $service->consume($inventory, 10);
    }

    public function test_waste_decrements_stock()
    {
        $strategy = Mockery::mock(RestockStrategyInterface::class);
        $service = new InventoryManagementService($strategy);
        
        $inventory = Inventory::factory()->create(['quantity' => 10]);
        
        $service->waste($inventory, 3);
        
        $this->assertEquals(7, $inventory->fresh()->quantity);
    }
}