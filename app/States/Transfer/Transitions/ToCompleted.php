<?php

namespace App\States\Transfer\Transitions;

use App\Events\TransferCompleted;
use App\Models\Transfer;
use App\States\Transfer\Completed;
use Spatie\ModelStates\Transition;

class ToCompleted extends Transition
{
    public function __construct(
        protected Transfer $transfer,
    ) {}

    public function handle(): Transfer
    {
        $this->transfer->status = new Completed($this->transfer);
        $this->transfer->save();

        event(new TransferCompleted($this->transfer));

        return $this->transfer;
    }
}
