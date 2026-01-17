# Specification: Logistics Stabilization & Cleanup

## 1. Overview
This track focuses on resolving technical debt, architectural redundancies, and documentation desynchronization introduced during the implementation of the Hybrid Event-Topology and UI Integration systems. The goal is to ensure a production-ready foundation with strict data integrity, clean API interfaces, and comprehensive verification coverage.

## 2. Functional Requirements

### 2.1 Architectural Refinement & Cleanup
- **KPI Deduplication:** Refactor `GameController::calculateKPIs` to remove the redundant "Logistics Health" metric from the generic KPI array, ensuring it is only passed as a dedicated top-level prop.
- **API Standardization:** Update the `LogisticsService` pathfinding response to include an `is_premium` flag. This flag must be algorithmically set whenever a route is selected as an alternative recovery path during a primary route blockage.
- **UX Standardization:** Formally adopt the "Informational Blocking" standard for the Restock Form. The UI should keep blocked primary options visible to display status messages, while preventing form submission. Update any conflicting documentation to reflect this standard.

### 2.2 Data Integrity & Persistence
- **Route Integrity:** Audit and update `Route` and `Location` migrations to enforce strict foreign key constraints at the database level.
- **DAG Fields:** Verify the persistence and utilization of `parent_id` and `type` fields in the `SpikeEvent` model to ensure causal chains are correctly represented in the simulation cycle and historical logs.

### 2.3 Documentation & Archive Synchronization
- **Plan Synchronization:** Perform a manual audit of the archived `hybrid_event_topology_20260116/plan.md` and mark all functionally complete tasks as `[x]`.
- **Verification Logs:** Ensure all manual verification scripts in `tests/manual/` are functional and documented.

## 3. Technical Requirements

### 3.1 Gameplay Loop Testing
The system must be validated through a complete state-transition lifecycle to ensure the simulation engine behaves predictably:
- **Sequence:** Initial State (Day 1) -> Decision-making State -> Stochastic States (Day 2+).
- **Initial State (Day 1):** Verify that Day 1 is always a stable, deterministic initial state where no random events are active.
- **Decision-Making State:** Verify that user actions (e.g., placing orders, defining policies) are correctly processed and persist into the next simulation tick.
- **Stochastic States (Day 2+):** Using logic derived from `tests/Feature/ChaosEngineTest.php`, verify that the `SpikeEventFactory` activates at Day 2, introducing random disruptions that correctly modify the physical and causal graphs without corrupting the underlying state.

### 3.2 Inventory Layer Gameplay Mechanics
The simulation must accurately process the following gameplay mechanics across multi-day cycles:
- **Inventory & Delivery:** 
    - Order delivery must increment inventory at target locations atomically.
    - Multi-product orders must update corresponding inventory entries.
    - Missing inventory records must be created automatically upon first delivery.
- **Logistics Timing:**
    - Delivery days must respect route-specific `transit_days` (e.g., Air vs. Truck).
    - Multiple orders scheduled for the same day must deliver concurrently.
- **Economic Layer (Storage Costs):**
    - Daily storage costs (based on product-specific `storage_cost` and quantity) must be deducted from `GameState` cash during each simulation tick.
    - Costs must scale correctly across multiple products and locations.
    - System must handle zero-cost products and prevent/handle negative cash balances according to design.
- **Player Agency (Cancellations):**
    - Orders in valid states (e.g., Pending/Shipped) can be cancelled, triggering cash refunds and preventing inventory updates.
    - Delivered orders must block cancellation attempts.
- **Logistics Constraints:**
    - Route `capacity` must limit the total volume of simultaneous shipments.
    - Route `max_daily_shipments` (throughput) must limit the number of successful shipments per day, queuing or rejecting overflows.

### 3.3 Advanced Stress Testing
- **Scenario A (The Cascade):** A single Root Spike (e.g., Blizzard) must trigger 10+ Symptom Alerts (Isolation) across the graph. Verify that system recovery (Route restoration and Alert resolution) occurs automatically upon spike expiration.
- **Scenario B (The Decision Stressor):** Simulate a state where multiple concurrent spikes (Price + Demand + Route Breakdown) force complex pathfinding recalculations. Verify that "Decision-Making" state transitions accurately reflect on the server side.
- **Scenario C (The Recursive Resolution):** Verify the integrity of multi-level causal chains (Root -> Symptom -> Task). Trigger a Root Event that spawns a Symptom Alert and a dependent Task. Verify that resolving the Root Event correctly propagates state changes down the entire chain, while individual Task resolution does not prematurely terminate the parent Spike.

### 3.3 Performance & Reliability
- **Pathfinding Efficiency:** Verify Dijkstra performance on a graph with 20+ nodes to ensure simulation ticks remain within performance budgets.
- **Test Alignment:** Ensure all new verification logic adheres to the patterns established in `tests/Feature/ChaosEngineTest.php`.

## 4. Acceptance Criteria
- Inertia dashboard receives no redundant props.
- `Route` table has active foreign key constraints for `source_id` and `target_id`.
- Pathfinding API returns a boolean `is_premium` flag for alternative routes.
- A comprehensive manual verification script (`tests/manual/verify_stabilization_v1.php`) passes a 10-day simulation cycle with overlapping spikes.
- Day 1 remains a stable "Initial State" for every simulation run.
- Archived plans are 100% synchronized with the actual codebase state.
