# Implementation Plan - Hybrid Event-Topology

## Phase 1: Physical Graph Foundation (Logistics) [checkpoint: 529dec3]
- [x] Task: Create `Route` Model and Migration
    - [x] Create migration for `routes` table (source_id, target_id, transport_mode, weights, is_active).
    - [x] Create `Route` Eloquent model with relationships to `Location`.
    - [x] Create Factory for `Route` to support testing.
- [x] Task: Implement `GraphSeeder`
    - [x] Create `GraphSeeder` to generate a Hub-and-Spoke topology.
    - [x] Ensure `Stores` connect to `Warehouses`, and `Warehouses` to `Vendors`.
    - [x] Add lateral connections (Store-to-Store) and "Air Routes" for redundancy.
- [x] Task: Implement `LogisticsService` (Basic)
    - [x] Create `LogisticsService` class.
    - [x] Implement `getValidRoutes($source, $target)` to return active edges.
    - [x] Implement basic cost calculation methods.
- [x] Task: TDD - Verify Physical Graph Construction
    - [x] Write tests to verify graph connectivity and `Route` attribute persistence.
    - [x] Verify `LogisticsService` correctly filters inactive routes.
- [x] Task: Conductor - User Manual Verification 'Physical Graph Foundation' (Protocol in workflow.md)

## Phase 2: Causal Graph & Event Propagation [checkpoint: 2432a15]
- [x] Task: Update `SpikeEvent` Model
    - [x] Add `affected_route_id` (nullable) or polymorphic relation to `SpikeEvent`.
    - [x] Add recursive relationship fields (`parent_id`, `type`) for DAG structure (Root/Symptom/Task).
- [x] Task: Implement `SpikeEventFactory` Updates
    - [x] Update factory to generate "Graph-Targeting" events (e.g., Blizzard targeting Road routes).
    - [x] Implement logic to select target `Routes` based on `weather_vulnerability`.
- [x] Task: Implement Event Listeners for Route State
    - [x] Create `SpikeStarted` listener: Sets `Route->is_active = false` or increases weight.
    - [x] Create `SpikeEnded` listener: Restores `Route->is_active = true` and weights.
- [x] Task: TDD - Verify Event Propagation
    - [x] Write tests confirming a "Blizzard" event disables vulnerable routes.
    - [x] Verify routes are restored when the event expires.
- [x] Task: Conductor - User Manual Verification 'Causal Graph & Event Propagation' (Protocol in workflow.md)

## Phase 3: Advanced Algorithms (BFS & Dijkstra) [checkpoint: 02af56d]
- [x] Task: Implement BFS Reachability in `LogisticsService`
    - [x] Implement `checkReachability(Location $target)` using Reverse-BFS.
    - [x] Return boolean indicating if *any* supply source is accessible.
- [x] Task: Implement Dijkstra Pathfinding in `LogisticsService`
    - [x] Implement `findBestRoute(Location $source, Location $target)` minimizing dynamic cost.
    - [x] Incorporate time/urgency multipliers into the weight calculation.
- [x] Task: TDD - Verify Graph Algorithms
    - [x] Test BFS correctly identifies isolated nodes.
    - [x] Test Dijkstra finds the cheapest valid path, avoiding blocked routes.
- [x] Task: Conductor - User Manual Verification 'Advanced Algorithms' (Protocol in workflow.md)

## Phase 4: Simulation Loop Integration [checkpoint: b184d77]
- [x] Task: Implement Alert Generation Logic
    - [x] Create `GenerateIsolationAlert` listener or service method.
    - [x] Logic: If `Reachability == false` and `Stock < Low`, create `Alert` linked to the active `SpikeEvent`.
- [x] Task: Update `SimulationService` Tick
    - [x] Integrate "Event Tick" (Update Spikes).
    - [x] Integrate "Physics Tick" (Move Transfers).
    - [x] Integrate "Analysis Tick" (Run BFS and Generate Alerts).
- [x] Task: TDD - Verify Simulation Loop
    - [x] Simulate a tick where a Blizzard blocks supply, and verify an Alert is spawned for a low-stock store.
- [x] Task: Conductor - User Manual Verification 'Simulation Loop Integration' (Protocol in workflow.md)

## Phase 5: UI Integration ("The No-Map Dashboard") [checkpoint: e739122]
- [x] Task: Update Dashboard Logic
    - [x] Expose "Logistics Health" metric (percentage of active routes vs total) via Inertia props.
    - [x] Update `StatWidget` or create `LogisticsStatusWidget` to display this metric.
- [x] Task: Enhance Transfer Form Logic
    - [x] Update `Inventory/Restock.tsx` (or equivalent) to query `LogisticsService` via a new Controller endpoint (`/api/logistics/routes`).
    - [x] Display "Unable to Restock" error if no valid routes exist (Reachability == False).
    - [x] Implement "Alternative Route Suggestion":
        - [x] If primary route blocked, display valid alternatives (e.g., "Air Drop") with their high cost highlighted.
- [x] Task: Conductor - User Manual Verification 'UI Integration' (Protocol in workflow.md)
