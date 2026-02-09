<?php

namespace App\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class TransferState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Transfer\Draft::class)
            ->registerState([
                Transfer\Draft::class,
                Transfer\InTransit::class,
                Transfer\Completed::class,
                Transfer\Cancelled::class,
            ])
            ->allowTransition(Transfer\Draft::class, Transfer\InTransit::class)
            ->allowTransition(Transfer\InTransit::class, Transfer\Completed::class, Transfer\Transitions\ToCompleted::class)
            ->allowTransition([Transfer\Draft::class, Transfer\InTransit::class], Transfer\Cancelled::class);
    }
}
