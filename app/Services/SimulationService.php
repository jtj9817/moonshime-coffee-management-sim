<?php

namespace App\Services;

use App\Actions\GenerateIsolationAlerts;
use App\Events\SpikeEnded;
use App\Events\SpikeOccurred;
use App\Events\TimeAdvanced;
use App\Models\GameState;
use App\Models\SpikeEvent;
use App\Models\Transfer;
use App\States\Transfer\Completed;
use App\States\Transfer\InTransit;

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
        // Clear logistics cache to ensure pathfinding respects new state (spikes, route status)
        app(\App\Services\LogisticsService::class)->clearCache();

        \Illuminate\Support\Facades\DB::transaction(function () {
            $this->gameState->increment('day');
            $this->gameState->refresh();
            $day = $this->gameState->day;

            $this->processEventTick($day);
            $this->processPhysicsTick($day);
            $this->processConsumptionTick($day);
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
        $constraintChecker = app(SpikeConstraintChecker::class);

        // 1. End spikes that reach their ends_at_day
        SpikeEvent::where('user_id', $userId)
            ->where('is_active', true)
            ->where('ends_at_day', '<=', $day)
            ->get()
            ->each(function (SpikeEvent $spike) {
                $spike->update([
                    'is_active' => false,
                    'resolved_at' => now(),
                    'resolved_by' => 'time',
                ]);
                event(new SpikeEnded($spike));
            });

        // 2. GUARANTEED: Ensure at least one spike covers today (after Day 1)
        $this->ensureGuaranteedSpike($day);

        // 3. Start spikes that reach their starts_at_day (includes guaranteed spikes created above)
        SpikeEvent::where('user_id', $userId)
            ->where('is_active', false)
            ->where('starts_at_day', '<=', $day)
            ->where('ends_at_day', '>', $day)
            ->get()
            ->each(function (SpikeEvent $spike) use ($constraintChecker, $day) {
                $spike->update(['is_active' => true]);
                event(new SpikeOccurred($spike));

                // Record cooldown when spike actually starts
                $constraintChecker->recordSpikeStarted($this->gameState, $spike->type, $day);
            });

        // 4. OPTIONAL: Schedule a future spike when constraints allow it
        $this->scheduleOptionalSpike($day, $constraintChecker);
    }

    /**
     * Ensure at least one spike covers today (guaranteed spike generation).
     */
    protected function ensureGuaranteedSpike(int $day): void
    {
        if ($day <= 1) {
            return; // Tutorial grace period
        }

        $userId = $this->gameState->user_id;

        // Check if any spike already covers today (active or scheduled-to-start today)
        $hasSpikeCoveringToday = SpikeEvent::where('user_id', $userId)
            ->where('starts_at_day', '<=', $day)
            ->where('ends_at_day', '>', $day)
            ->exists();

        if (! $hasSpikeCoveringToday) {
            // Generate a guaranteed spike for today
            app(GuaranteedSpikeGenerator::class)->generate($this->gameState, $day);
        }
    }

    /**
     * Optionally schedule a future spike if cap/cooldown constraints allow it.
     */
    protected function scheduleOptionalSpike(int $day, SpikeConstraintChecker $constraintChecker): void
    {
        $startDay = $day + 1;
        $duration = rand(2, 5);

        if (! $constraintChecker->canScheduleSpike($this->gameState, $startDay, $duration)) {
            return;
        }

        $allowedTypes = $constraintChecker->getAllowedTypes($this->gameState, $startDay);
        if (empty($allowedTypes)) {
            return;
        }

        app(\App\Services\SpikeEventFactory::class)->generateWithConstraints(
            userId: $this->gameState->user_id,
            allowedTypes: $allowedTypes,
            startDay: $startDay,
            duration: $duration,
            isGuaranteed: false,
            useWeights: true
        );
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
     * Consumption Tick: Process daily customer demand and inventory depletion.
     */
    protected function processConsumptionTick(int $day): void
    {
        app(DemandSimulationService::class)->processDailyConsumption($this->gameState, $day);
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
