<?php

namespace App\States\Order\Transitions;

use App\Events\OrderDelivered;
use App\Models\Order;
use App\States\Order\Delivered;
use Spatie\ModelStates\Transition;

class ToDelivered extends Transition
{
    public function __construct(
        protected Order $order,
    ) {}

    public function handle(): Order
    {
        $this->order->status = new Delivered($this->order);
        $this->order->save();

        event(new OrderDelivered($this->order));

        return $this->order;
    }
}
