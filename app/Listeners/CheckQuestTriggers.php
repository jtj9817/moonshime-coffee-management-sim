<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Events\TimeAdvanced;
use App\Events\TransferCompleted;
use App\Models\User;
use App\Services\QuestService;

class CheckQuestTriggers
{
    public function __construct(
        protected QuestService $questService
    ) {}

    /**
     * Handle TimeAdvanced event.
     */
    public function onTimeAdvanced(TimeAdvanced $event): void
    {
        $user = $event->gameState->user;
        if ($user) {
            $this->questService->checkTriggers($user);
        }
    }

    /**
     * Handle OrderPlaced event.
     */
    public function onOrderPlaced(OrderPlaced $event): void
    {
        $user = User::find($event->order->user_id);
        if ($user) {
            $this->questService->checkTriggers($user);
        }
    }

    /**
     * Handle TransferCompleted event.
     */
    public function onTransferCompleted(TransferCompleted $event): void
    {
        $user = User::find($event->transfer->user_id);
        if ($user) {
            $this->questService->checkTriggers($user);
        }
    }
}
