# Moonshine Coffee Management Sim - Technical Design Document

## 1. Executive Summary

Moonshine Coffee Management Sim is a simulation game focusing on coffee shop inventory and supply chain operations. This document serves as the **Single Source of Truth** for the project's architecture, database schema, design patterns, and implementation roadmap.

The system is built on a **Hybrid Architecture** combining a robust Laravel 12 backend (Logic, State, Database) with a React 19 frontend (UI, Interaction) connected via Inertia.js.

The core simulation engine utilizes a **Hybrid Event-Topology** model, distinguishing between the *Physical Graph* (Logistics/Routes) and the *Causal Graph* (Events/Consequences).

---

## 2. Technology Stack

*   **Backend:** PHP 8.2+, Laravel 12
    *   **Auth:** Laravel Fortify
    *   **Database:** PostgreSQL
    *   **Routing:** Laravel Wayfinder (Type-safe routes)
    *   **State Management:** `spatie/laravel-model-states` (Finite State Machines)
*   **Frontend:** React 19, TypeScript
    *   **Glue:** Inertia.js 2.0 (SSR enabled)
    *   **Styling:** Tailwind CSS 4.0
    *   **Build:** Vite
    *   **Components:** Headless UI / Radix primitives
*   **Infrastructure:** Docker (Laravel Sail)

---

## 3. Architectural Standards & Design Patterns

We adhere to strict architectural guidelines to ensure scalability and maintainability.

### 3.1 Strict Dependency Injection
*   **Rule:** Controllers must **never** contain business logic.
*   **Implementation:** All logic is encapsulated in **Services** or **Repositories** and injected via the constructor.
*   **Binding:** Use `GameServiceProvider` to bind interfaces to implementations (e.g., `AiProviderInterface` -> `PrismAiService`).

### 3.2 Event-Driven Architecture (Pub/Sub)
*   **Concept:** Decouple primary business actions from side effects.
*   **Flow:**
    1.  **Action:** Service performs task (e.g., `OrderService::placeOrder`).
    2.  **Event:** Dispatches event (e.g., `OrderPlaced`).
    3.  **Listener:** Handles side effects (e.g., `DeductCash`, `NotifyWarehouse`).

### 3.3 Finite State Machines
*   **Context:** Complex lifecycles for `Order` and `Transfer` models.
*   **Tool:** `spatie/laravel-model-states`.
*   **Benefit:** Enforces valid transitions and encapsulates transition logic (e.g., preventing shipping without stock).

### 3.4 Strategy Pattern
*   **Context:** Variable logic based on user configuration (e.g., Policy settings).
*   **Implementation:** `RestockStrategyInterface` allows hot-swapping logic (e.g., `JustInTimeStrategy` vs `SafetyStockStrategy`) without `if/else` chains in services.

### 3.5 Data Transfer Objects (DTOs)
*   **Context:** Passing data between Services, AI, and Controllers.
*   **Rule:** Use `readonly` PHP classes (e.g., `InventoryContextDTO`) instead of associative arrays to ensure type safety.

---

## 4. Domain Modeling & Database Schema

### 4.1 Core Entities (Implemented)

| Model | Table | Responsibility | Key Relationships |
| :--- | :--- | :--- | :--- |
| **Location** | `locations` | Stores, Warehouses | `hasMany(Inventory)`, `hasMany(Route)` |
| **Vendor** | `vendors` | Suppliers | `hasMany(Order)` |
| **Product** | `products` | Items/SKUs | `hasMany(Inventory)` |
| **Inventory** | `inventories` | Stock levels | `belongsTo(Location)`, `belongsTo(Product)` |
| **Order** | `orders` | Procurement | `hasMany(OrderItem)`, State Machine |
| **Transfer** | `transfers` | Logistics | `source_location`, `target_location`, State Machine |

### 4.2 Simulation & Graph Entities (Planned/In-Progress)

| Model | Table | Responsibility | Key Attributes |
| :--- | :--- | :--- | :--- |
| **Route** | `routes` | The "Edges" of the physical graph | `source_id`, `target_id`, `transit_days`, `cost`, `capacity` |
| **SpikeEvent** | `spike_events` | Root causes in the Causal Graph | `type`, `severity`, `affected_route_id` |
| **Alert** | `alerts` | Symptoms/Notifications | `spike_event_id`, `message`, `severity` |
| **GameState** | `game_states` | Global player state (Singleton) | `cash`, `day`, `reputation` |

---

## 5. The Hybrid Event-Topology Engine

The simulation engine divides the game world into two graph structures.

### 5.1 The Physical Graph (Logistics Layer)
*   **Type:** Directed Weighted Multigraph (Cyclic).
*   **Components:**
    *   **Nodes:** `Location` (Store, Warehouse).
    *   **Edges:** `Route`.
*   **Logic:** Dictates *where* goods move, *cost*, and *time*.
*   **Algorithms:**
    *   **BFS:** Check "Reachability" (Is a store besieged?).
    *   **Dijkstra:** Calculate "Shortest Path" for transfers based on Cost/Time.

### 5.2 The Causal Graph (Event Layer)
*   **Type:** Directed Acyclic Graph (DAG).
*   **Components:**
    *   **Root:** `SpikeEvent` (e.g., Blizzard).
    *   **Child:** `Alert` (e.g., "Store A Isolated").
    *   **Leaf:** `Task` (Player resolution).
*   **Logic:** Events disable `Routes` in the Physical Graph.

### 5.3 The Simulation Loop (`SimulationService`)
The `advanceTime()` method orchestrates the interaction:
1.  **Event Tick:** Update `SpikeEvents`. If active, disable associated `Routes`.
2.  **Physics Tick:** Move active `Transfers` along valid `Routes`. Consume `Inventory`.
3.  **Analysis Tick:** Run **BFS Reachability**. If Store Stock < Low AND Reachability == False, spawn Critical Alert.

---

## 6. Frontend Architecture

### 6.1 Inertia.js & Routing
*   **Routing:** All routes defined in `routes/web.php`.
*   **Wayfinder:** Use generated TypeScript route helpers (e.g., `routes.orders.store()`) for type-safe URL generation.
*   **State:** Global game state (`cash`, `day`) injected via `HandleInertiaRequests` middleware.

### 6.2 Persistent Layouts
*   **GameLayout:** Wraps all game pages. Persists the Sidebar and Topbar (HUD) to prevent re-rendering during navigation.

### 6.3 Type Safety
*   **DTOs -> TS:** PHP DTOs and Resources are automatically transformed into TypeScript interfaces in `resources/js/types` using `spatie/laravel-typescript-transformer`.

---

## 7. Implementation Roadmap

This roadmap focuses on realizing the **Hybrid Event-Topology** and finalizing the system migration.

### Phase 1: Physical Graph Foundation (Logistics)
- [ ] **Database:** Create `routes` table with weights (cost, time, capacity) and constraints.
- [ ] **Seeder:** Implement `GraphSeeder` to generate a connected world topology (Hub-and-Spoke).
- [ ] **Service:** Implement `LogisticsService` with `getValidRoutes($source, $target)` and basic path cost calculation.

### Phase 2: Causal Event System (Advanced Simulation)
- [ ] **Model Update:** Refactor `SpikeEvent` to include polymorphic targeting (targeting `Route` or `Location`).
- [ ] **Factory:** Update `SpikeEventFactory` to generate graph-aware events (e.g., "Road Closure" blocking specific Routes).
- [ ] **Listeners:** Create listeners that toggle `Route->is_active` based on Spike start/end events.

### Phase 3: Advanced Algorithms & Analysis
- [ ] **Pathfinding:** Implement **Dijkstra’s Algorithm** in `LogisticsService` for optimal transfer suggestions.
- [ ] **Reachability:** Implement **BFS** in `SimulationService` to detect isolated nodes during the Analysis Tick.
- [ ] **Integration:** Wire `SimulationService` to trigger Alerts when isolation is detected.

### Phase 4: UI Integration
- [ ] **Visualization:** Create a "Logistics Map" or "Route Status" dashboard.
- [ ] **UX:** Update Transfer forms to respect Graph constraints (disable transfers if no Route exists).
- [ ] **Feedback:** Display "Why" feedback (e.g., "Route Blocked by Blizzard") using the Causal Graph links.
