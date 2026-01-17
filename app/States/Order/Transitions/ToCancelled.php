<?php

namespace App\States\Order\Transitions;

use App\Events\OrderCancelled;
use App\Models\Order;
use App\States\Order\Cancelled;
use Spatie\ModelStates\Transition;

class ToCancelled extends Transition
{
    public function __construct(
        protected Order $order,
    ) {}

    public function handle(): Order
    {
        $this->order->status = new Cancelled($this->order);
        $this->order->save();

        event(new OrderCancelled($this->order));

        return $this->order;
    }
}
