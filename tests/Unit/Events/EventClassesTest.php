<?php

namespace Tests\Unit\Events;

use App\Events\OrderPlaced;
use App\Events\SpikeOccurred;
use App\Events\TimeAdvanced;
use App\Events\TransferCompleted;
use App\Models\Order;
use App\Models\Transfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventClassesTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_placed_event_holds_order()
    {
        $order = Order::factory()->make();
        $event = new OrderPlaced($order);

        $this->assertSame($order, $event->order);
    }

    public function test_transfer_completed_event_holds_transfer()
    {
        $transfer = Transfer::factory()->make();
        $event = new TransferCompleted($transfer);

        $this->assertSame($transfer, $event->transfer);
    }

    public function test_spike_occurred_event_holds_spike_data()
    {
        // Using a generic object until SpikeEvent model is implemented in Phase 3
        $spike = (object) ['type' => 'heatwave'];
        $event = new SpikeOccurred($spike);

        $this->assertSame($spike, $event->spike);
    }

    public function test_time_advanced_event_holds_day()
    {
        $event = new TimeAdvanced(5);

        $this->assertEquals(5, $event->day);
    }
}
