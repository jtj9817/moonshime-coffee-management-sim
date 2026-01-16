# Specification: Game Logic & Events (Step 4)

## Overview
This track implements the core "Simulation Engine" and its associated event-driven logic. It establishes the "Game Loop" through a manual turn-based clock, state machines for complex lifecycles, and a "Chaos Monkey" factory for random disruptions.

## Functional Requirements

### 1. Event-Driven Logic (DAG)
Implement a robust Event/Listener system where side effects follow a strict logical DAG.
- **Trigger:** `OrderPlaced`, `TransferCompleted`, `SpikeOccurred`.
- **Chain of Execution:**
    1. **Cash Management:** Validate and deduct funds. If funds are insufficient, the entire chain MUST abort (Hard Block).
    2. **Alerts:** Generate an `Alert` model entry summarizing the event.
    3. **Inventory Updates:** Adjust stock levels (e.g., decrementing source, incrementing target, or marking as in-transit).
    4. **Analytics/XP:** Update player XP and Vendor Reliability metrics based on the transaction outcome.

### 2. State Machines
Implement finite state machines using `spatie/laravel-model-states` for:
- **`Order` Model:** `Draft` -> `Pending` -> `Shipped` -> `Delivered` -> `Cancelled`.
- **`Transfer` Model:** `Draft` -> `InTransit` -> `Completed` -> `Cancelled`.
- **Transitions:** Each transition must validate the "Cash Trigger" before proceeding.

### 3. Simulation Service (The Clock)
- **Clock Type:** Manual (Turn-based).
- **Trigger:** An `advanceTime()` method (triggered by player action) that increments the game "Day".
- **Execution Hook:** On time advancement, fire a `TimeAdvanced` event that:
    - Triggers the `SpikeEventFactory`.
    - Processes pending arrivals for `Orders` and `Transfers`.
    - Applies decay or daily costs.

### 4. Spike Event Factory (Chaos Engine)
Centralized factory for generating weighted random disruptions:
- **Demand Spike:** Increases consumption rates for specific products.
- **Supply Chain Delay:** Postpones the `delivery_date` of active orders.
- **Price Fluctuation:** Temporarily modifies vendor prices.
- **Resource Breakdown:** Temporarily reduces storage capacity in a `Location`.

## Non-Functional Requirements
- **Atomicity:** All event chains involving inventory or cash must run within a Database Transaction.
- **Decoupling:** Listeners must be isolated; failure in the "Analytics" listener should not necessarily roll back the "Inventory" change (unless it's part of the core DAG).

## Acceptance Criteria
- [ ] Calling `SimulationService::advanceTime()` increments the game state day and fires appropriate events.
- [ ] Placing an order with insufficient cash throws an exception and prevents any model updates.
- [ ] Orders successfully transition through all states, triggering inventory changes only at the appropriate steps.
- [ ] The `SpikeEventFactory` can be manually invoked to produce a valid disruption object.

## Out of Scope
- Real-time WebSocket updates (Laravel Reverb) - to be handled in Step 5.
- Complex UI for "Chaos Monitor" - only the backend logic and Alert generation are in scope.
