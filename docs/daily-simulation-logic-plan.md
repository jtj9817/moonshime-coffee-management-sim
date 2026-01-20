# Daily Simulation Logic Implementation Plan

**Created**: 2026-01-20
**Completed**: In Progress
**Status**: 🔴 Not Started
**Purpose**: Implement the game loop logic required to advance multi-hop shipments from source to destination as game days progress.

---

## Problem Statement
Start of the "Multi-Hop" system introduced `Shipment` records to track individual legs of a journey. However, there is currently no mechanism to process these records. As the game day advances, shipments remain static in their initial states (`in_transit` or `pending`).

1. **[Stagnant Shipments]**: `in_transit` shipments never arrive.
2. **[Broken Chains]**: Intermediate stops (like Hubs) do not trigger the next leg of the journey; goods get stuck at the first stop.
3. **[Missing Inventory]**: Final delivery never occurs, so the target store never receives the stock.

Without this logic, the entire logistics system is purely cosmetic and functional only in theory.

---

## Design Decisions (Stakeholder Preferences)

| Decision | Choice |
| :--- | :--- |
| **Processing Location** | `SimulationService` (centralized game loop handler) |
| **State Transitions** | Immediate hand-off (Arrival at Hub = Instant departure of next leg) |
| **Inventory Update** | Only on **Final Destination** arrival (simplified handling) |

---

## Solution Architecture

### Overview
```
Game Loop (Advance Day)
     ↓
[Process Shipments]
     ↓
Fetch Active Shipments (in_transit) where arrival_day <= current_day
     ↓
For Each Arrived Shipment:
     ↓
   [Update Status: DELIVERED]
     ↓
   Is this the Final Leg?
     Yes → [Update Order: DELIVERED] → [Increase Store Inventory]
     No  → [Find Next Shipment in Sequence] → [Update Next Shipment: IN_TRANSIT]
```

---

## Implementation Tasks

### Phase 1: Core Simulation Logic 🔴

#### Task 1.1: Implement `processShipments` in SimulationService 🔴
**File**: `app/Services/SimulationService.php`

```php
public function processShipments(GameState $gameState)
{
    // Find all active shipments that have theoretically arrived
    $arrivedShipments = Shipment::where('status', 'in_transit')
        ->where('arrival_day', '<=', $gameState->day)
        ->get();

    foreach ($arrivedShipments as $shipment) {
        $this->handleShipmentArrival($shipment);
    }
}
```

**Key Logic/Responsibilities**:
* Identify shipments that have completed their transit time.
* Delegate the specific arrival logic to a handler method.

#### Task 1.2: Handle Shipment Arrival & Transitions 🔴
**File**: `app/Services/SimulationService.php` (or `LogisticsService`)

```php
protected function handleShipmentArrival(Shipment $shipment)
{
    // 1. Mark current leg as completed
    $shipment->update(['status' => 'delivered']);

    // 2. Check for next leg
    $nextLeg = Shipment::where('order_id', $shipment->order_id)
        ->where('sequence_index', $shipment->sequence_index + 1)
        ->first();

    if ($nextLeg) {
        // Continue the chain
        $nextLeg->update([
            'status' => 'in_transit',
            // Recalculate arrival day based on current day? 
            // Or trust the pre-calculated one? PRE-CALCULATED is better for prediction reliability.
        ]);
        // Note: If we had delays, we would update the next leg's arrival_day here.
    } else {
        // Final Destination Reached
        $this->handleFinalDelivery($shipment->order);
    }
}
```

**Key Logic/Responsibilities**:
* Transition the current shipment to `delivered`.
* Trigger the immediately following shipment to `in_transit`.
* Identify if the order is fully complete.

#### Task 1.3: Final Delivery & Inventory Update 🔴
**File**: `app/Services/SimulationService.php`

```php
protected function handleFinalDelivery(Order $order)
{
    $order->status->transitionTo(\App\States\Order\Delivered::class);
    
    // Add items to inventory
    foreach ($order->items as $item) {
        Inventory::updateOrCreate(
            [
                'location_id' => $order->location_id,
                'product_id' => $item->product_id,
                'user_id' => $order->user_id,
            ],
            [
                'quantity' => DB::raw("quantity + {$item->quantity}")
            ]
        );
    }
}
```

**Key Logic/Responsibilities**:
* Update the parent `Order` status to `Delivered`.
* Increment inventory at the target location.

---

## File Summary

| File | Action | Status |
| :--- | :--- | :--- |
| `app/Services/SimulationService.php` | Modify | 🔴 |
| `app/Models/Shipment.php` | Modify | 🔴 |
| `tests/Feature/DailySimulationTest.php` | Create | 🔴 |

---

## Execution Order
1. **[Create Test]** — Create a Feature test that sets up a shipment, advances the day, and asserts the status changes.
2. **[Implement Logic]** — Add the shipping processing methods to `SimulationService`.
3. **[Integrate Loop]** — Ensure `advanceTime` calls `processShipments`.
4. **[Refine]** — Handle edge cases like "Arrival Day < Current Day" (catch-up).

---

## Edge Cases to Handle
1. **[Missed Days]**: If the game jumps multiple days, shipments that arrived "yesterday" must still be processed. **Logic**: Use `<=` comparison for arrival day. 🔴
2. **[Concurrent Arrivals]**: Multiple shipments arriving at the Hub on the same day. **Logic**: Iterate loop handles this naturally. 🔴
3. **[Empty Next Leg]**: Data corruption where next leg is missing. **Logic**: Fallback to checking `total_transit_days` or log error, but treat current as delivered. 🔴

---

## Rollback Plan
1. Revert changes to `SimulationService`.
2. Delete the new test file.

---

## Success Criteria
- [ ] Active shipments transition to `delivered` when `gamestate->day` matches `arrival_day`.
- [ ] Intermediate stops automatically trigger the next leg.
- [ ] Final delivery correctly increments inventory.
- [ ] Test suite passes with 100% coverage of the new flow.

---

## Implementation Walkthrough
*Pending execution.*
