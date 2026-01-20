# Multi-Hop Order Logic Proposal

## Overview
This document explores the architectural changes required to support **Multi-Hop Orders**. Currently, the system assumes a direct `Route` exists between a Vendor and a Store. However, our graph (Vendor -> Hub -> Store) requires goods to travel through intermediate nodes.

## The Core Concept
Instead of an `Order` being a single point-to-point movement, an `Order` becomes a **container** for a journey that may involve multiple **Shipments** (or **Legs**).

**Scenario:** Ordering *Arabica Beans* from *Bean Baron* to *Moonshine Central*.
- **Direct Route:** None exists.
- **Path:** Bean Baron -> Central Transit Hub -> Moonshine Central.

## Proposed Architecture

### 1. Database Schema Changes

#### Option A: The "Chain of Shipments" (Recommended)
We keep the `Order` acting as the "Master Record" (the financial transaction with the Vendor), but we decouple the physical movement into child records.

**New Model: `Shipment` (or `OrderLeg`)**
```php
Schema::create('shipments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained(); // Belongs to the parent Order
    $table->foreignId('route_id')->constrained(); // The specific edge being traversed
    $table->foreignId('source_location_id');
    $table->foreignId('target_location_id');
    $table->integer('sequence_index'); // 0 = First leg, 1 = Second leg
    $table->string('status'); // pending, in_transit, delivered, failed
    $table->date('arrival_date');
});
```

**Changes to `Order` Model:**
- Remove `route_id` (since an order uses multiple routes).
- Remove `delivery_date` (or keep it as the *final* estimated delivery).
- `status` now reflects the overall progress (e.g., `processing` -> `shipped` -> `at_hub` -> `final_delivery` -> `completed`).

### 2. The Logistics Logic (The "Brain")

When a user places an order:

1.  **Pathfinding:**
    The `LogisticsService` runs Dijkstra's algorithm (already implemented as `findBestRoute`) to get a sequence of Routes: `[Route A (Vendor->Hub), Route B (Hub->Store)]`.

2.  **Order Creation:**
    The `OrderService` creates the parent `Order` record.

3.  **Shipment Generation:**
    The system generates `Shipment` records for each step of the path.
    - **Shipment 1:** Vendor -> Hub. Status: `in_transit`. ETA: Day + 1.
    - **Shipment 2:** Hub -> Store. Status: `pending`. ETA: Day + 1 + TransitTime.

### 3. The Daily Ticker (Simulation Loop)

The "End of Day" processing becomes smarter:

- **Day 1 Ends:**
    - System checks active `Shipments`.
    - **Shipment 1** arrives at Hub. Status -> `delivered`.
    - **Trigger Logic:** When a shipment arrives at an intermediate node (Hub), the system automatically *activates* the next shipment in the sequence.
    - **Shipment 2** Status -> `in_transit`. Goods "move" conceptually from the incoming truck to the outgoing truck.

- **Day 2 Ends:**
    - **Shipment 2** arrives at Store. Status -> `delivered`.
    - System checks if this was the *last* leg.
    - Yes? Mark parent `Order` as `completed`. Add inventory to Store.

### 4. Handling Inventory at Intermediate Nodes

This is the tricky part. Do the goods strictly exist in "Limbo" or do they temporarily enter the Hub's inventory?

- **Simple Approach (Cross-docking):** Goods never technically enter the Hub's `Inventory` table. They stay in the `Shipment` object. The Hub is just a "Switch".
- **Realism Approach:** Goods enter Hub inventory but are "Reserved" for the outbound shipment.

**Recommendation:** Use the **Simple Approach** first. Goods live in the `Shipment` record until the final destination. This avoids complex inventory locking logic at the Hub.

### 5. UI/UX Implications

**Ordering Dialog:**
- **User Selection:** User picks Vendor + Store.
- **System Response:** "No direct route. Suggested Route: via Central Hub (est. 4 days)."
- **Cost Display:** Sum of cost of all legs.

**Order History / Tracking:**
- Instead of "Status: Shipped", the user sees a timeline:
    - [x] Left Vendor (Day 1)
    - [x] Arrived at Hub (Day 2)
    - [>] In Transit to Store (ETA Day 3)

## Pros & Cons

| Feature | Pros | Cons |
| :--- | :--- | :--- |
| **Realism** | Mimics real logic chains vs. teleportation. | Significantly increases code complexity. |
| **Flexibility** | Allows blocking specific legs (e.g., "Airport Closed") without killing all commerce. | Harder to debug "stuck" orders. |
| **Scalability** | Supports infinite hops/intermodal transport. | Database grows faster (1 order = N shipments). |

## Migration Plan (If chosen)

1.  **Refactor Database:** Create `shipments` table.
2.  **Migrate Data:** Convert existing single-route orders into 1-leg shipments.
3.  **Update LogisticsService:** Expose pathfinding to the Ordering Controller.
4.  **Update Job/Command:** meaningful "ProcessArrivals" logic to handle multi-leg handoffs.
