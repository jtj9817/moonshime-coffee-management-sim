<?php

use App\Events\TimeAdvanced;
use App\Models\GameState;
use App\Models\Order;
use App\Models\SpikeEvent;
use App\Models\User;
use App\Models\Vendor;
use App\Services\GuaranteedSpikeGenerator;
use App\Services\SimulationService;
use App\Services\SpikeEventFactory;
use App\States\Order\Delivered;
use App\States\Order\Shipped;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function disableRandomSpikesForSimulationServiceTests(): void
{
    $generator = Mockery::mock(GuaranteedSpikeGenerator::class);
    $generator->shouldReceive('generate')->andReturn(null);
    app()->instance(GuaranteedSpikeGenerator::class, $generator);

    $factory = Mockery::mock(SpikeEventFactory::class)->makePartial();
    $factory->shouldReceive('generateWithConstraints')->andReturn(null);
    app()->instance(SpikeEventFactory::class, $factory);
}

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
    disableRandomSpikesForSimulationServiceTests();

    $vendor = Vendor::factory()->create();
    $order = Order::factory()->create([
        'vendor_id' => $vendor->id,
        'status' => Shipped::class,
        'delivery_day' => 2,
        'user_id' => $this->gameState->user_id,
    ]);

    $this->service->advanceTime(); // Day becomes 2

    expect($order->fresh()->status)->toBeInstanceOf(Delivered::class);
});

test('it applies delay spikes deterministically to delivery timelines', function () {
    disableRandomSpikesForSimulationServiceTests();

    $vendor = Vendor::factory()->create();
    $order = Order::factory()->create([
        'vendor_id' => $vendor->id,
        'status' => Shipped::class,
        'delivery_day' => 2,
        'user_id' => $this->gameState->user_id,
    ]);

    SpikeEvent::create([
        'user_id' => $this->gameState->user_id,
        'type' => 'delay',
        'magnitude' => 2,
        'duration' => 3,
        'location_id' => null,
        'product_id' => null,
        'starts_at_day' => 2,
        'ends_at_day' => 5,
        'is_active' => false,
        'meta' => null,
    ]);

    $this->service->advanceTime(); // Day 2, delay spike activates and shifts delivery_day by +2

    expect($order->fresh()->status)->toBeInstanceOf(Shipped::class)
        ->and($order->fresh()->delivery_day)->toBe(4);

    $this->service->advanceTime(); // Day 3
    expect($order->fresh()->status)->toBeInstanceOf(Shipped::class);

    $this->service->advanceTime(); // Day 4
    expect($order->fresh()->status)->toBeInstanceOf(Delivered::class);
});

test('it triggers spike activation and future generation on time advancement', function () {
    // 1. Setup a spike that should start on Day 2
    $pendingSpike = SpikeEvent::factory()->create([
        'starts_at_day' => 2,
        'ends_at_day' => 4,
        'is_active' => false,
        'user_id' => $this->gameState->user_id,
    ]);

    // 2. Mock SpikeEventFactory to verify future generation is called
    // Note: We don't mock 'apply' here because it's called via event listener which we want to run

    $this->service->advanceTime(); // Advance to Day 2

    // 3. Assert pending spike is now active
    expect($pendingSpike->fresh()->is_active)->toBeTrue();

    // 4. Assert a new spike was generated (it will have starts_at_day = 3)
    // We check if any spike exists starting on Day 3
    expect(SpikeEvent::where('starts_at_day', 3)->exists())->toBeTrue();
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
