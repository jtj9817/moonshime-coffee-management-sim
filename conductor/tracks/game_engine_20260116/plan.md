# Implementation Plan: Game Logic & Events (Step 4)

## Phase 1: Events & Listeners (The DAG)
- [ ] Task: Define Event Classes
    - [ ] Create `app/Events/OrderPlaced.php`
    - [ ] Create `app/Events/TransferCompleted.php`
    - [ ] Create `app/Events/SpikeOccurred.php`
    - [ ] Create `app/Events/TimeAdvanced.php`
- [ ] Task: Implement DAG Listeners
    - [ ] Create `app/Listeners/DeductCash.php` (The Trigger/Validator)
    - [ ] Create `app/Listeners/GenerateAlert.php`
    - [ ] Create `app/Listeners/UpdateInventory.php`
    - [ ] Create `app/Listeners/UpdateMetrics.php` (XP/Reliability)
- [ ] Task: Register Event-Listener Mapping in `AppServiceProvider` or dedicated provider.
- [ ] Task: Create manual verification script `tests/manual/verify_dag_events.php` (using `laravel-manual-testing` skill).
- [ ] Task: Conductor - User Manual Verification 'Phase 1: Events & Listeners' (Protocol in workflow.md)

## Phase 2: State Machines
- [ ] Task: Install and Configure State Machine
    - [ ] Install `spatie/laravel-model-states` (if not already present).
    - [ ] Create base state classes for `Order` and `Transfer`.
- [ ] Task: Implement `Order` States
    - [ ] Define states: `Draft`, `Pending`, `Shipped`, `Delivered`, `Cancelled`.
    - [ ] Implement transition logic and validation (Cash check).
- [ ] Task: Implement `Transfer` States
    - [ ] Define states: `Draft`, `InTransit`, `Completed`, `Cancelled`.
- [ ] Task: Create manual verification script `tests/manual/verify_state_machines.php`.
- [ ] Task: Conductor - User Manual Verification 'Phase 2: State Machines' (Protocol in workflow.md)

## Phase 3: Chaos Engine (SpikeEventFactory)
- [ ] Task: Implement Spike Event Models & Types
    - [ ] Create `app/Models/SpikeEvent.php` and migration.
    - [ ] Create interfaces/classes for Spike types (Demand, Delay, Price, Breakdown).
- [ ] Task: Implement `SpikeEventFactory`
    - [ ] Implement weighted random selection logic.
    - [ ] Add methods to "apply" and "rollback" spike effects on models.
- [ ] Task: Create manual verification script `tests/manual/verify_chaos_engine.php`.
- [ ] Task: Conductor - User Manual Verification 'Phase 3: Chaos Engine' (Protocol in workflow.md)

## Phase 4: Simulation Service & Integration
- [ ] Task: Implement `SimulationService`
    - [ ] Implement `advanceTime()` method.
    - [ ] Ensure it fires `TimeAdvanced` and triggers Spike generation.
- [ ] Task: Final Wiring
    - [ ] Update `GameState` singleton to track current Day.
- [ ] Task: Create manual verification script `tests/manual/verify_game_integration.php`.
- [ ] Task: Conductor - User Manual Verification 'Phase 4: Simulation Service & Integration' (Protocol in workflow.md)
