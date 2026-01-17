# Specification: Hybrid Event-Topology System

## 1. Overview
This track implements the "Hybrid Event-Topology" architecture, replacing simple point-to-point logistics with a graph-based simulation engine. The system divides the game world into two interacting graph structures:
1.  **Physical Graph (Logistics):** A cyclic weighted multigraph representing Stores, Warehouses, Vendors, and Transit Hubs connected by Routes.
2.  **Causal Graph (Events):** A Directed Acyclic Graph (DAG) representing the dependency chain of Events (Root Causes), Alerts (Symptoms), and Tasks (Resolutions).

## 2. Functional Requirements

### 2.1 The Physical Graph (Logistics Layer)
-   **Graph Nodes:** Implement `Location` entities representing Stores, Warehouses, Vendors, and Transit Hubs.
-   **Graph Edges (Routes):** Implement `Route` model with:
    -   `transport_mode` (Truck, Air, Ship).
    -   `weather_vulnerability` (Boolean).
    -   `reliability_score` (Probability factor).
    -   `weight_cost` and `weight_time`.
    -   `is_active` (Boolean status).

### 2.2 The Propagation Flow (Graph Interaction)
The system must support a three-tier dependency flow within the Causal Graph:
1.  **Root Node (The Spark):** The `SpikeEventFactory` generates a `SpikeEvent` (e.g., "Blizzard").
    -   *Effect:* Identifies susceptible `Routes` in the Physical Graph and modifies their state (e.g., setting `is_active = false` or increasing weights).
2.  **Child Node (The Symptom):** The `SimulationService` detects a Store is unreachable via BFS.
    -   *Creation:* Automatically spawns an `Alert` linked to the parent `SpikeEvent`.
    -   *Message:* "Store A is cut off from supply."
3.  **Leaf Node (The Task):** The system generates a `Task` requiring user action.
    -   *Goal:* "Secure alternate supply."
    -   *Resolution:* The user finds an alternate path via Dijkstra (e.g., an expensive Air route) and executes a Transfer, or acknowledges/resolves the alert to clear the symptom.

### 2.3 Logistics Service & Algorithms
-   **Pathfinding:** Implement Dijkstra's algorithm in `LogisticsService` to find the optimal path based on dynamic weights.
-   **Reachability:** Implement Reverse-BFS from a target location to determine if any source (Warehouse/Vendor) is accessible on active edges.

### 2.4 Simulation Integration
-   **Tick Processing:**
    1.  **Event Tick:** Advance timers on `SpikeEvents`. If a spike expires, restore affected `Route` states.
    2.  **Physics Tick:** Process active `Transfers` along their designated routes.
    3.  **Analysis Tick:** Run **Reachability (BFS)** on all Stores. If `Stock < Low` AND `Reachability == False`, spawn a "Critical Isolation" `Alert` as a child of the blocking `SpikeEvent`.

## 3. Non-Functional Requirements
-   **Performance:** Graph traversal algorithms must be optimized for execution within the simulation tick.
-   **Persistence:** The Causal Graph must be stored in a way that preserves the "Story" of a disruption for historical analysis.

## 4. Acceptance Criteria
-   **Event-Graph Coupling:** A "Blizzard" event correctly blocks specific routes in the Physical Graph.
-   **Automatic Symptom Spawning:** An `Alert` is created automatically when a store becomes isolated.
-   **Dijkstra Pathfinding:** The system suggests valid alternate routes when primary routes are blocked.
-   **Resolution Loop:** The `Alert/Task` remains active until acknowledged or the underlying event expires.
