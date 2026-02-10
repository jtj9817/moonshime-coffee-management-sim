<?php

use App\Models\GameState;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\Route;
use App\Models\ScheduledOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ScheduledOrderService;
use App\Services\SimulationService;
use App\States\Order\Draft;
use App\States\Order\Pending;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function buildScheduledOrderWorld(int $cash = 100000): array
{
    $user = User::factory()->create();
    $gameState = GameState::factory()->create([
        'user_id' => $user->id,
        'day' => 1,
        'cash' => $cash,
    ]);

    $vendor = Vendor::factory()->create();
    $sourceLocation = Location::factory()->create(['type' => 'vendor']);
    $targetLocation = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create([
        'unit_price' => 250,
    ]);

    Route::factory()->create([
        'source_id' => $sourceLocation->id,
        'target_id' => $targetLocation->id,
        'transport_mode' => 'Ship',
        'cost' => 250,
        'capacity' => 100,
        'transit_days' => 2,
        'is_active' => true,
        'weather_vulnerability' => false,
    ]);

    return compact(
        'user',
        'gameState',
        'vendor',
        'sourceLocation',
        'targetLocation',
        'product',
    );
}

it('creates pending orders from due auto-submit schedules on day advance', function () {
    $world = buildScheduledOrderWorld(100000);

    ScheduledOrder::create([
        'user_id' => $world['user']->id,
        'vendor_id' => $world['vendor']->id,
        'source_location_id' => $world['sourceLocation']->id,
        'location_id' => $world['targetLocation']->id,
        'items' => [
            [
                'product_id' => $world['product']->id,
                'quantity' => 10,
                'unit_price' => 200,
            ],
        ],
        'interval_days' => 7,
        'next_run_day' => 2,
        'auto_submit' => true,
        'is_active' => true,
    ]);

    $this->actingAs($world['user']);
    $simulation = new SimulationService($world['gameState']);
    $simulation->advanceTime();

    $order = Order::where('user_id', $world['user']->id)->latest()->first();
    $schedule = ScheduledOrder::where('user_id', $world['user']->id)->firstOrFail()->fresh();

    expect($world['gameState']->fresh()->day)->toBe(2)
        ->and($order)->not()->toBeNull()
        ->and($order->status)->toBeInstanceOf(Pending::class)
        ->and($order->created_day)->toBe(2)
        ->and($order->total_cost)->toBe(2250)
        ->and($world['gameState']->fresh()->cash)->toBe(97750)
        ->and($schedule->last_run_day)->toBe(2)
        ->and($schedule->next_run_day)->toBe(9)
        ->and($schedule->failure_reason)->toBeNull();
});

it('does not auto-submit scheduled orders when funds are insufficient', function () {
    $world = buildScheduledOrderWorld(500);

    ScheduledOrder::create([
        'user_id' => $world['user']->id,
        'vendor_id' => $world['vendor']->id,
        'source_location_id' => $world['sourceLocation']->id,
        'location_id' => $world['targetLocation']->id,
        'items' => [
            [
                'product_id' => $world['product']->id,
                'quantity' => 3,
                'unit_price' => 200,
            ],
        ],
        'interval_days' => 3,
        'next_run_day' => 2,
        'auto_submit' => true,
        'is_active' => true,
    ]);

    $this->actingAs($world['user']);
    $simulation = new SimulationService($world['gameState']);
    $simulation->advanceTime();

    $schedule = ScheduledOrder::where('user_id', $world['user']->id)->firstOrFail()->fresh();

    expect(Order::where('user_id', $world['user']->id)->count())->toBe(0)
        ->and($world['gameState']->fresh()->cash)->toBe(500)
        ->and($schedule->last_run_day)->toBe(2)
        ->and($schedule->next_run_day)->toBe(5)
        ->and($schedule->failure_reason)->toContain('Insufficient funds');
});

it('creates draft orders for non-auto-submit schedules', function () {
    $world = buildScheduledOrderWorld(500);

    ScheduledOrder::create([
        'user_id' => $world['user']->id,
        'vendor_id' => $world['vendor']->id,
        'source_location_id' => $world['sourceLocation']->id,
        'location_id' => $world['targetLocation']->id,
        'items' => [
            [
                'product_id' => $world['product']->id,
                'quantity' => 3,
                'unit_price' => 200,
            ],
        ],
        'interval_days' => 3,
        'next_run_day' => 2,
        'auto_submit' => false,
        'is_active' => true,
    ]);

    $this->actingAs($world['user']);
    $simulation = new SimulationService($world['gameState']);
    $simulation->advanceTime();

    $order = Order::where('user_id', $world['user']->id)->latest()->first();

    expect($order)->not()->toBeNull()
        ->and($order->status)->toBeInstanceOf(Draft::class)
        ->and($world['gameState']->fresh()->cash)->toBe(500);
});

it('rolls back scheduled auto-submit orders when execution fails after createOrder', function () {
    $world = buildScheduledOrderWorld(100000);

    ScheduledOrder::create([
        'user_id' => $world['user']->id,
        'vendor_id' => $world['vendor']->id,
        'source_location_id' => $world['sourceLocation']->id,
        'location_id' => $world['targetLocation']->id,
        'items' => [
            [
                'product_id' => $world['product']->id,
                'quantity' => 2,
                'unit_price' => 200,
            ],
        ],
        'interval_days' => 3,
        'next_run_day' => 2,
        'auto_submit' => true,
        'is_active' => true,
    ]);

    Event::listen(\App\Events\OrderPlaced::class, function (): void {
        throw new RuntimeException('forced schedule failure');
    });

    app(ScheduledOrderService::class)->processDueSchedules($world['gameState'], 2);

    $schedule = ScheduledOrder::where('user_id', $world['user']->id)->firstOrFail()->fresh();

    expect(Order::where('user_id', $world['user']->id)->count())->toBe(0)
        ->and($world['gameState']->fresh()->cash)->toBe(100000)
        ->and($schedule->last_run_day)->toBe(2)
        ->and($schedule->next_run_day)->toBe(5)
        ->and($schedule->failure_reason)->toContain('forced schedule failure');
});
