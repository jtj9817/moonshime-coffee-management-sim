<?php

namespace App\Services;

use App\Models\GameState;
use App\Events\TimeAdvanced;
use App\Models\SpikeEvent;
use App\Models\Transfer;
use App\States\Transfer\InTransit;
use App\States\Transfer\Completed;
use App\Actions\GenerateIsolationAlerts;
use App\Events\SpikeOccurred;
use App\Events\SpikeEnded;

class SimulationService
{
    public function __construct(
        protected GameState $gameState
    ) {}

    /**
     * Advance the game simulation by one day.
     */
    public function advanceTime(): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () {
            $this->gameState->increment('day');
            $this->gameState->refresh();
            $day = $this->gameState->day;

            $this->processEventTick($day);
            $this->processPhysicsTick($day);
            $this->processAnalysisTick($day);
            
            event(new TimeAdvanced($day, $this->gameState));
        });
    }

    /**
     * Event Tick: Update Spike lifecycle (Activate/Deactivate) and generate new spikes.
     */
    protected function processEventTick(int $day): void
    {
        $userId = $this->gameState->user_id;

        // 1. End spikes that reach their ends_at_day
        SpikeEvent::where('user_id', $userId)
            ->where('is_active', true)
            ->where('ends_at_day', '<=', $day)
            ->get()
            ->each(function (SpikeEvent $spike) {
                $spike->update(['is_active' => false]);
                event(new SpikeEnded($spike));
            });

        // 2. Start spikes that reach their starts_at_day
        SpikeEvent::where('user_id', $userId)
            ->where('is_active', false)
            ->where('starts_at_day', '<=', $day)
            ->where('ends_at_day', '>', $day)
            ->get()
            ->each(function (SpikeEvent $spike) {
                $spike->update(['is_active' => true]);
                event(new SpikeOccurred($spike));
            });

        // 3. Generate a new spike for the future (Optional/Random)
        app(\App\Services\SpikeEventFactory::class)->generate($day);
    }

    /**
     * Physics Tick: Move active transfers (Process Deliveries).
     */
    protected function processPhysicsTick(int $day): void
    {
        $userId = $this->gameState->user_id;

        Transfer::where('user_id', $userId)
            ->whereState('status', InTransit::class)
            ->where('delivery_day', '<=', $day)
            ->get()
            ->each(fn ($transfer) => $transfer->status->transitionTo(Completed::class));

        \App\Models\Order::where('user_id', $userId)
            ->whereState('status', \App\States\Order\Shipped::class)
            ->where('delivery_day', '<=', $day)
            ->get()
            ->each(fn ($order) => $order->status->transitionTo(\App\States\Order\Delivered::class));
    }

    /**
     * Analysis Tick: Run BFS and Generate Isolation Alerts.
     */
    protected function processAnalysisTick(int $day): void
    {
        app(GenerateIsolationAlerts::class)->handle($this->gameState->user_id);
    }

    /**
     * Get the current game day.
     */
    public function getCurrentDay(): int
    {
        return $this->gameState->day;
    }
}
