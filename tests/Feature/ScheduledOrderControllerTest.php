<?php

use App\Models\GameState;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\ScheduledOrder;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function buildSchedulePayloadWorld(): array
{
    $user = User::factory()->create();
    GameState::factory()->create([
        'user_id' => $user->id,
        'day' => 4,
        'cash' => 100000,
    ]);

    $vendor = Vendor::factory()->create();
    $sourceLocation = Location::factory()->create(['type' => 'vendor']);
    $targetLocation = Location::factory()->create(['type' => 'store']);
    $product = Product::factory()->create();
    Inventory::factory()->create([
        'user_id' => $user->id,
        'location_id' => $targetLocation->id,
        'product_id' => $product->id,
        'quantity' => 0,
    ]);

    return compact('user', 'vendor', 'sourceLocation', 'targetLocation', 'product');
}

it('stores scheduled orders from ordering flow', function () {
    $world = buildSchedulePayloadWorld();

    $response = $this->actingAs($world['user'])
        ->from('/game/ordering')
        ->post('/game/orders/scheduled', [
            'vendor_id' => $world['vendor']->id,
            'source_location_id' => $world['sourceLocation']->id,
            'location_id' => $world['targetLocation']->id,
            'interval_days' => 7,
            'auto_submit' => true,
            'items' => [
                [
                    'product_id' => $world['product']->id,
                    'quantity' => 9,
                    'unit_price' => 180,
                ],
            ],
        ]);

    $response->assertSessionHasNoErrors();

    $schedule = ScheduledOrder::where('user_id', $world['user']->id)->latest()->first();

    expect($schedule)->not()->toBeNull()
        ->and($schedule->next_run_day)->toBe(5)
        ->and($schedule->interval_days)->toBe(7)
        ->and($schedule->auto_submit)->toBeTrue();
});

it('rejects scheduled orders for destination locations not in user inventory scope', function () {
    $world = buildSchedulePayloadWorld();
    $otherUser = User::factory()->create();
    $foreignLocation = Location::factory()->create(['type' => 'store']);
    Inventory::factory()->create([
        'user_id' => $otherUser->id,
        'location_id' => $foreignLocation->id,
        'product_id' => $world['product']->id,
        'quantity' => 10,
    ]);

    $response = $this->actingAs($world['user'])
        ->from('/game/ordering')
        ->post('/game/orders/scheduled', [
            'vendor_id' => $world['vendor']->id,
            'source_location_id' => $world['sourceLocation']->id,
            'location_id' => $foreignLocation->id,
            'interval_days' => 7,
            'items' => [
                [
                    'product_id' => $world['product']->id,
                    'quantity' => 5,
                    'unit_price' => 180,
                ],
            ],
        ]);

    $response->assertSessionHasErrors('location_id');
    expect(ScheduledOrder::where('user_id', $world['user']->id)->count())->toBe(0);
});

it('rejects unsupported cron expressions for scheduled orders', function () {
    $world = buildSchedulePayloadWorld();

    $response = $this->actingAs($world['user'])
        ->from('/game/ordering')
        ->post('/game/orders/scheduled', [
            'vendor_id' => $world['vendor']->id,
            'source_location_id' => $world['sourceLocation']->id,
            'location_id' => $world['targetLocation']->id,
            'cron_expression' => '0 0 * * *',
            'items' => [
                [
                    'product_id' => $world['product']->id,
                    'quantity' => 5,
                    'unit_price' => 180,
                ],
            ],
        ]);

    $response->assertSessionHasErrors('cron_expression');
    expect(ScheduledOrder::where('user_id', $world['user']->id)->count())->toBe(0);
});

it('enforces user isolation for schedule toggles and deletes', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $schedule = ScheduledOrder::factory()->create([
        'user_id' => $owner->id,
    ]);

    $this->actingAs($intruder)
        ->patch("/game/orders/scheduled/{$schedule->id}/toggle")
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete("/game/orders/scheduled/{$schedule->id}")
        ->assertForbidden();
});

it('toggles and deletes own scheduled orders', function () {
    $owner = User::factory()->create();
    $schedule = ScheduledOrder::factory()->create([
        'user_id' => $owner->id,
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->patch("/game/orders/scheduled/{$schedule->id}/toggle")
        ->assertRedirect();

    expect($schedule->fresh()->is_active)->toBeFalse();

    $this->actingAs($owner)
        ->delete("/game/orders/scheduled/{$schedule->id}")
        ->assertRedirect();

    expect(ScheduledOrder::whereKey($schedule->id)->exists())->toBeFalse();
});
