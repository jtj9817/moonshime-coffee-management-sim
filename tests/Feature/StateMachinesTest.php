<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Transfer;
use App\States\OrderState;
use App\States\TransferState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StateMachinesTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_has_state_machine()
    {
        $order = Order::factory()->create();

        $this->assertInstanceOf(OrderState::class, $order->status);
    }

    public function test_transfer_has_state_machine()
    {
        $transfer = Transfer::factory()->create();

        $this->assertInstanceOf(TransferState::class, $transfer->status);
    }

    public function test_order_can_transition_to_pending_with_enough_cash()
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        \App\Models\GameState::factory()->create([
            'user_id' => $user->id,
            'cash' => 5000,
        ]);

        $order = Order::factory()->create(['total_cost' => 3000]);

        $order->status->transitionTo(\App\States\Order\Pending::class);

        $this->assertInstanceOf(\App\States\Order\Pending::class, $order->fresh()->status);
    }

    public function test_order_cannot_transition_to_pending_with_insufficient_cash()
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        \App\Models\GameState::factory()->create([
            'user_id' => $user->id,
            'cash' => 1000,
        ]);

        $order = Order::factory()->create(['total_cost' => 3000]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient funds to place order.');

        $order->status->transitionTo(\App\States\Order\Pending::class);
    }

    public function test_order_can_transition_through_full_lifecycle()
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);
        \App\Models\GameState::factory()->create(['user_id' => $user->id, 'cash' => 10000]);

        $order = Order::factory()->create(['total_cost' => 1000]);

        $order->status->transitionTo(\App\States\Order\Pending::class);
        $this->assertInstanceOf(\App\States\Order\Pending::class, $order->status);

        $order->status->transitionTo(\App\States\Order\Shipped::class);
        $this->assertInstanceOf(\App\States\Order\Shipped::class, $order->status);

        $order->status->transitionTo(\App\States\Order\Delivered::class);
        $this->assertInstanceOf(\App\States\Order\Delivered::class, $order->status);
    }

    public function test_transfer_can_transition_through_full_lifecycle()
    {
        $transfer = Transfer::factory()->create();

        $transfer->status->transitionTo(\App\States\Transfer\InTransit::class);
        $this->assertInstanceOf(\App\States\Transfer\InTransit::class, $transfer->status);

        $transfer->status->transitionTo(\App\States\Transfer\Completed::class);
        $this->assertInstanceOf(\App\States\Transfer\Completed::class, $transfer->status);
    }
}
