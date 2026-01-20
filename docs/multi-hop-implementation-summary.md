# Multi-Hop Order Implementation Summary

## Context
Initial reviews of the routing logic (`docs/route-routing-review.md`) identified a critical limitation: the system relied on direct routes (Vendor → Store), but the graph topology was designed as a hub-and-spoke model (Vendor → Hub, Hub → Store, etc.). This resulted in:
1.  **Orphaned Stores**: The main store created by seeders was isolated from the graph.
2.  **No Valid Routes**: Users physically couldn't place orders because no direct edges existed between vendors and stores.
3.  **Buggy Seeding**: Initial game state creation attempted to force invalid routes, leading to data inconsistencies.

## Solution Overview
We implemented a **Multi-Hop Order System** that treats an order as a container for a chain of **Shipments**. This allows goods to traverse multiple legs (e.g., Vendor → Hub → Store) to reach their destination.

## Technical Implementation

### 1. Database Schema
- **New `shipments` Table**: Tracks individual legs of a journey (`source`, `target`, `route_id`, `status`, `arrival_day`).
- **Updated `orders` Table**: Removed the single `route_id` column and added `total_transit_days` to cache the full journey duration.

### 2. Backend Logic
- **`OrderService`**: A new domain service responsible for:
    - Recursively creating `Shipment` records based on the calculated path.
    - Linking all shipments to the parent Order.
    - Calculating total costs (Item Cost + Sum of Route Costs).
- **`LogisticsService` integration**: The system now utilizes the existing Dijkstra's algorithm to find the optimal path across the entire graph, rather than just looking for direct neighbours.
- **Validation (`StoreOrderRequest`)**:
    - Validates order quantity against the **minimum capacity** of the entire path (the bottleneck).
    - Validates total cost against user funds.
    - Passes the calculated path to the controller to ensure what is validated is exactly what is executed.

### 3. Frontend (`new-order-dialog.tsx`)
- **Path Selection**: Users no longer select a "Route". They select a **Source** (Vendor/Hub) and a **Destination**.
- **Visualization**: The dialog fetches the calculated path from the backend and displays each leg (e.g., "Leg 1: Air Freight (Vendor -> Hub)", "Leg 2: Truck (Hub -> Store)").
- **Transparency**: Displays total transit time and the limiting capacity of the chosen route.

### 4. Graph & Seeding Integrity
- **Graph Wiring**: Fixed `GraphSeeder` and `CoreGameStateSeeder` to ensures `Moonshine Central` (and all other stores) are properly connected to the Hub.
- **Initialization**: `InitializeNewGame` now uses `OrderService` to create the initial historical orders, ensuring that even the pre-seeded data respects the multi-hop logic.

## Verification
- **Automated Testing**: A new feature test (`tests/Feature/MultiHopOrderTest.php`) validates the end-to-end flow:
    - Placing an order creates the correct number of `Shipment` records.
    - Costs and transit times are aggregated correctly.
    - Source and target locations for each shipment match the route topology.

## Future Work
- **Daily Simulation**: The next phase involves updating the daily game tick logic to process these `Shipment` records, advancing them from `in_transit` to `delivered` or to the next leg in the chain as game days progress.
