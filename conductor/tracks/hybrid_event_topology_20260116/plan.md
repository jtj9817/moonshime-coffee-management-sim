# Implementation Plan - Hybrid Event-Topology

## Phase 1: Physical Graph Foundation (Logistics) [checkpoint: 529dec3]
- [x] Task: Create `Route` Model and Migration
    - [ ] Create migration for `routes` table (source_id, target_id, transport_mode, weights, is_active).
    - [ ] Create `Route` Eloquent model with relationships to `Location`.
    - [ ] Create Factory for `Route` to support testing.
- [x] Task: Implement `GraphSeeder`
    - [ ] Create `GraphSeeder` to generate a Hub-and-Spoke topology.
    - [ ] Ensure `Stores` connect to `Warehouses`, and `Warehouses` to `Vendors`.
    - [ ] Add lateral connections (Store-to-Store) and "Air Routes" for redundancy.
- [x] Task: Implement `LogisticsService` (Basic)
    - [ ] Create `LogisticsService` class.
    - [ ] Implement `getValidRoutes($source, $target)` to return active edges.
    - [ ] Implement basic cost calculation methods.
- [x] Task: TDD - Verify Physical Graph Construction
    - [ ] Write tests to verify graph connectivity and `Route` attribute persistence.
    - [ ] Verify `LogisticsService` correctly filters inactive routes.
- [x] Task: Conductor - User Manual Verification 'Physical Graph Foundation' (Protocol in workflow.md)

## Phase 2: Causal Graph & Event Propagation
- [x] Task: Update `SpikeEvent` Model
    - [ ] Add `affected_route_id` (nullable) or polymorphic relation to `SpikeEvent`.
    - [ ] Add recursive relationship fields (`parent_id`, `type`) for DAG structure (Root/Symptom/Task).
- [x] Task: Implement `SpikeEventFactory` Updates
    - [ ] Update factory to generate "Graph-Targeting" events (e.g., Blizzard targeting Road routes).
    - [ ] Implement logic to select target `Routes` based on `weather_vulnerability`.
- [x] Task: Implement Event Listeners for Route State
    - [ ] Create `SpikeStarted` listener: Sets `Route->is_active = false` or increases weight.
    - [ ] Create `SpikeEnded` listener: Restores `Route->is_active = true` and weights.
- [x] Task: TDD - Verify Event Propagation
    - [x] Write tests confirming a "Blizzard" event disables vulnerable routes.
    - [x] Verify routes are restored when the event expires.
- [~] Task: Conductor - User Manual Verification 'Causal Graph & Event Propagation' (Protocol in workflow.md)

## Phase 3: Advanced Algorithms (BFS & Dijkstra)
- [ ] Task: Implement BFS Reachability in `LogisticsService`
    - [ ] Implement `checkReachability(Location $target)` using Reverse-BFS.
    - [ ] Return boolean indicating if *any* supply source is accessible.
- [ ] Task: Implement Dijkstra Pathfinding in `LogisticsService`
    - [ ] Implement `findBestRoute(Location $source, Location $target)` minimizing dynamic cost.
    - [ ] Incorporate time/urgency multipliers into the weight calculation.
- [ ] Task: TDD - Verify Graph Algorithms
    - [ ] Test BFS correctly identifies isolated nodes.
    - [ ] Test Dijkstra finds the cheapest valid path, avoiding blocked routes.
- [ ] Task: Conductor - User Manual Verification 'Advanced Algorithms' (Protocol in workflow.md)

## Phase 4: Simulation Loop Integration
- [ ] Task: Implement Alert Generation Logic
    - [ ] Create `GenerateIsolationAlert` listener or service method.
    - [ ] Logic: If `Reachability == false` and `Stock < Low`, create `Alert` linked to the active `SpikeEvent`.
- [ ] Task: Update `SimulationService` Tick
    - [ ] Integrate "Event Tick" (Update Spikes).
    - [ ] Integrate "Physics Tick" (Move Transfers).
    - [ ] Integrate "Analysis Tick" (Run BFS and Generate Alerts).
- [ ] Task: TDD - Verify Simulation Loop
    - [ ] Simulate a tick where a Blizzard blocks supply, and verify an Alert is spawned for a low-stock store.
- [ ] Task: Conductor - User Manual Verification 'Simulation Loop Integration' (Protocol in workflow.md)

## Phase 5: UI Integration ("The No-Map Dashboard")
- [ ] Task: Update Dashboard Logic
    - [ ] Expose "Logistics Health" metric (percentage of active routes vs total) via Inertia props.
    - [ ] Update `StatWidget` or create `LogisticsStatusWidget` to display this metric.
- [ ] Task: Enhance Transfer Form Logic
    - [ ] Update `Inventory/Restock.tsx` (or equivalent) to query `LogisticsService` via a new Controller endpoint (`/api/logistics/routes`).
    - [ ] Display "Unable to Restock" error if no valid routes exist (Reachability == False).
    - [ ] Implement "Alternative Route Suggestion":
        - [ ] If primary route blocked, display valid alternatives (e.g., "Air Drop") with their high cost highlighted.
- [ ] Task: Conductor - User Manual Verification 'UI Integration' (Protocol in workflow.md)
