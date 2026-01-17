# Moonshine Architecture: Hybrid Event-Topology System

## 1. Executive Summary

This document defines the architectural standard for the "Hybrid Event-Topology" system in Moonshine. This system replaces simple point-to-point logic with a graph-based simulation engine. It divides the game world into two distinct graph structures:
1.  **The Physical Graph (Cyclic):** A "World Map" of Stores, Warehouses, and Routes that dictates the flow of goods.
2.  **The Causal Graph (DAG):** A dependency tree of Events, Consequences, and Tasks that dictates the flow of state changes.

**Core Philosophy:** The User manages the *Physical Graph* (Logistics) to resolve challenges created by the *Causal Graph* (Events).

---

## 2. The Physical Graph (Logistics Layer)

The Physical Graph represents the "Layout" of the supply chain. It dictates *where* goods can move, *how much* it costs, and *how long* it takes.

### 2.1 Graph Theory Definition
*   **Type:** Directed Weighted Multigraph (Cyclic).
*   **Nodes ($V$):** `Locations` (Stores, Warehouses, Vendors).
*   **Edges ($E$):** `Routes`.
*   **Weights ($W$):** Cost, Time, Capacity.
*   **Cycles:** Allowed and encouraged (e.g., balancing stock between two stores: $A \to B$ and $B \to A$).
*   **Multigraph:** Multiple edges allowed between nodes (e.g., "Truck Route" vs "Air Route").

### 2.2 Data Model: `Route`
The `Route` entity is the Edge of this graph.

```php
class Route extends Model {
    // Relationships
    source_id: Location (Node A)
    target_id: Location (Node B)
    
    // Weights
    transit_days: int (Time Weight)
    cost_per_unit: float (Cost Weight)
    capacity_per_day: int (Flow Constraint)
    
    // Meta
    type: string (e.g., 'truck', 'plane')
    is_active: boolean
}
```

### 2.3 Algorithms (LogisticsService)
We leverage standard graph algorithms to power game decisions.

#### A. Reachability (BFS - Breadth-First Search)
*   **Use Case:** "Can Store A currently receive stock from *anywhere*?"
*   **Logic:** Run Reverse-BFS from Store A on `is_active` edges. If a `Warehouse` or `Vendor` is visited, Reachability = True.
*   **Game Impact:** If Reachability is False, the Store is effectively "Besieged" (Critical State).

#### B. Shortest Path (Dijkstra's Algorithm)
*   **Use Case:** "Auto-Select Best Source" for transfers.
*   **Logic:** Minimize $Cost = (Distance \times CostMultiplier) + (Time \times UrgencyMultiplier)$.
*   **Game Impact:** The UI can smartly suggest: "Transfer from Store B is cheaper ($50) than Warehouse ($200) due to current Route spikes."

---

## 3. The Causal Graph (Event Layer)

The Causal Graph represents the "Story" of the simulation. It ensures that problems have root causes and solutions have logical prerequisites.

### 3.1 Graph Theory Definition
*   **Type:** Directed Acyclic Graph (DAG).
*   **Nodes:** `SpikeEvents` (Root Causes), `Alerts` (Symptoms), `Tasks` (Player Actions).
*   **Edges:** "Caused By" / "Requires" dependencies.
*   **Constraint:** Strictly Acyclic. No time paradoxes (Effect cannot precede Cause).

### 3.2 Data Model Structure
We utilize a recursive structure (Self-Referencing) to build the DAG.

```php
// Conceptual Model
Event Node {
    id: UUID
    parent_id: UUID (The "Cause")
    type: 'ROOT_CAUSE' | 'SYMPTOM' | 'TASK'
    status: 'ACTIVE' | 'RESOLVED'
}
```

### 3.3 The Propagation Flow
1.  **Root Node (The Spark):** The `SpikeEventFactory` generates a `SpikeEvent` (e.g., "Blizzard").
    *   *Effect:* Finds `Routes` in the Physical Graph and sets `is_active = false`.
2.  **Child Node (The Symptom):** The Simulation Tick detects a Store is unreachable.
    *   *Creation:* Spawns an `Alert` linked to the `SpikeEvent`.
    *   *Message:* "Store A is cut off from supply."
3.  **Leaf Node (The Task):** The User interface generates a `Task`.
    *   *Goal:* "Secure alternate supply."
    *   *Resolution:* User finds a path via Dijkstra (using unblocked edges) and executes a Transfer.

---

## 4. Integration Logic

### 4.1 The Simulation Tick
The `SimulationService::advanceTime()` method orchestrates the interaction between the two graphs.

1.  **Event Tick (Update DAG):**
    *   Advance timers on all active `SpikeEvents`.
    *   If a Spike expires, revert its changes to the Physical Graph (Restore Routes).
2.  **Physics Tick (Traverse Physical Graph):**
    *   For every `Active Transfer`: Move goods along the `Route`.
    *   For every `Store`: Calculate Consumption.
3.  **Analysis Tick (Update DAG):**
    *   Check Store Inventory levels.
    *   Run **Reachability (BFS)** on all Stores.
    *   If Stock < Low AND Reachability == False: Spawn "Critical Isolation" Alert (Child of the blocking Spike).

### 4.2 User Experience (The "No Map" Dashboard)
The user interacts with tables and policies, but the backend "Constraints" define the gameplay.

*   **Scenario:** User tries to "Quick Restock" Store A.
*   **Backend Check:**
    1.  `LogisticsService::findBestRoute(Source, Store A)`
    2.  **Result:** No path found (Routes blocked by "Blizzard").
*   **Frontend Feedback:**
    *   "Unable to Restock: All land routes blocked by Blizzard."
    *   "Action Available: Emergency Air Drop (Cost: 10x)."
    *   *(This reveals the "Air Route" edge which is immune to the "Blizzard" modifier but has high weight).*

---

## 5. Implementation Roadmap

### Phase 1: Physical Graph Foundation
1.  **Database:** Create `routes` table with weights and constraints.
2.  **Seeder:** `GraphSeeder` to generate a connected world (Hub-and-Spoke topology with some lateral connections).
3.  **Service:** `LogisticsService` with `getValidRoutes($source, $target)` and basic cost calculation.

### Phase 2: Causal Event System
1.  **Model:** Update `SpikeEvent` to include `affected_route_id` (or polymorphic targets).
2.  **Factory:** Update `SpikeEventFactory` to generate graph-targeting events (e.g., "Road Closure" vs "Global Demand Spike").
3.  **Listeners:** Event Listeners that modify `Route` states when Spikes start/end.

### Phase 3: Advanced Algorithms
1.  **Pathfinding:** Implement `Dijkstra` in `LogisticsService`.
2.  **Analysis:** Implement `BFS Reachability` check in `SimulationService`.

### Phase 4: UI Integration
1.  **Dashboard:** Display Route status (e.g., "Logistics Health: 80%").
2.  **Actions:** Update Transfer forms to respect Graph constraints.
