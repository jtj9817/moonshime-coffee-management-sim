<?php

namespace App\States;

use App\States\Order;
use App\States\Order\Transitions;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class OrderState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Order\Draft::class)
            ->registerState([
                Order\Draft::class,
                Order\Pending::class,
                Order\Shipped::class,
                Order\Delivered::class,
                Order\Cancelled::class,
            ])
            ->allowTransition(Order\Draft::class, Order\Pending::class, Transitions\ToPending::class)
            ->allowTransition(Order\Pending::class, Order\Shipped::class)
            ->allowTransition(Order\Shipped::class, Order\Delivered::class)
            ->allowTransition([Order\Draft::class, Order\Pending::class], Order\Cancelled::class);
    }
}
