<?php

use App\Events\TimeAdvanced;
use App\Models\GameState;
use App\Models\User;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\SpikeEvent;
use App\Services\SimulationService;
use App\Services\SpikeEventFactory;
use App\States\Order\Shipped;
use App\States\Order\Delivered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;

beforeEach(function () {
    $user = User::factory()->create();
    $this->gameState = GameState::factory()->create([
        'user_id' => $user->id,
        'day' => 1,
        'cash' => 10000,
    ]);

    // We need to act as the user so the singleton works if resolved from container
    $this->actingAs($user);
    
    // However, in our tests, we instantiate it manually for some tests
    $this->service = new SimulationService($this->gameState);
});

test('it increments the day in game state', function () {
    $this->service->advanceTime();

    expect($this->gameState->fresh()->day)->toBe(2);
});

test('it fires time advanced event', function () {
    Event::fake([TimeAdvanced::class]);

    $this->service->advanceTime();

    Event::assertDispatched(TimeAdvanced::class, function ($event) {
        return $event->day === 2;
    });
});

test('it processes deliveries on time advancement', function () {
    $vendor = Vendor::factory()->create();
    $order = Order::factory()->create([
        'vendor_id' => $vendor->id,
        'status' => Shipped::class,
        'delivery_day' => 2,
    ]);

    $this->service->advanceTime(); // Day becomes 2

    expect($order->fresh()->status)->toBeInstanceOf(Delivered::class);
});

test('it triggers spike generation on time advancement', function () {
    $mockFactory = $this->mock(SpikeEventFactory::class, function (MockInterface $mock) {
        $mock->shouldReceive('generate')
            ->once()
            ->with(2)
            ->andReturn(SpikeEvent::factory()->make(['starts_at_day' => 3]));
        
        $mock->shouldReceive('apply')->once();
    });

    // We need to ensure the listener uses the mocked factory.
    // Laravel's $this->mock() handles this if the factory is resolved from the container.
    
    $this->service->advanceTime(); // Day 2
});

test('it is atomic and rolls back on failure', function () {
    // We use a separate listener that throws an exception
    Event::listen(TimeAdvanced::class, function () {
        throw new \Exception('Simulated Failure');
    });

    try {
        $this->service->advanceTime();
    } catch (\Exception $e) {
        expect($e->getMessage())->toBe('Simulated Failure');
    }

    // Day should NOT have incremented due to rollback
    expect($this->gameState->fresh()->day)->toBe(1);
});