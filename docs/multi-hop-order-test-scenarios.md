# Multi-Hop Order Test Scenarios (Brainstorm)

Goal: expand `tests/Feature/MultiHopOrderTest.php` into a future-friendly regression suite that covers best/average/worst cases and edge conditions for multi-hop orders.

## Scenario Matrix (Best / Average / Worst)

| Scenario Type | Description | Primary Risk It Covers |
| --- | --- | --- |
| Best case | 2-hop chain with ample cash, capacity, active routes, single product | Happy path correctness (baseline regression) |
| Average case | 3–4 hops, mixed transport modes, moderate quantity, multiple products | Typical planning logic + cost aggregation |
| Worst case | 6–10 hops, tight cash, near-capacity routes, long transit times, multiple items | Performance limits + compounded costs + edge constraints |

## Concrete Scenario Ideas

### Best Case
- **Minimal viable multi-hop**: Vendor → Hub → Store with one product and small quantity.
- **Single product, single vendor**: ensures route selection + shipments are created in the correct sequence.
- **Simple cost model**: verify totals = item cost + sum(route costs) with exact rounding rules.

### Average Case
- **3–4 hops with mixed modes**: e.g., truck → rail → van → bike; assert the correct number of shipments and cost aggregation.
- **Multiple products in a single order**: verify per-item totals roll up into order total and shipments are still per-route (not per-product) unless design says otherwise.
- **Two possible routes** (one cheaper, one faster): assert the lowest-cost path is chosen.
- **Moderate capacity pressure**: order quantity near capacity but still feasible (e.g., 80–90% capacity).
- **Cash still positive**: game cash decreases by total cost and remains non-negative (if game rules enforce this).

### Worst Case
- **Long chain (6–10 hops)**: stresses sequencing, total cost, and shipment count.
- **Capacity at limit**: order equals capacity exactly; ensure it passes and does not overflow.
- **Tight cash**: order leaves minimal cash buffer; verify it still succeeds if allowed.
- **Large quantity**: tests integer/float precision, total cost rounding, and performance.
- **Multiple vendors + shared hubs**: ensure the correct vendor-source path is used for a given order.

## Edge Cases & Negative Scenarios (Regression-Focused)

- **No viable route**: no path from vendor to target location → expect validation failure or specific error response.
- **Inactive route in the middle**: ensure inactive legs are ignored; choose the lowest-cost active path or fail if none exist.
- **Zero or negative capacity**: route capacity is 0 or negative → ensure rejection.
- **Cycle detection**: routes forming a loop (A→B→C→A) → ensure algorithm avoids infinite loops.
- **Duplicate routes with different costs**: deterministic selection based on lowest total cost.
- **Missing vendor location**: vendor has no associated location; ensure error handling is explicit.
- **Source equals target**: ordering from vendor location to same location; expect validation error.
- **Rounding edge**: unit_price with 3+ decimals; verify rounding rules and exact totals.
- **Empty items list**: ensure validation fails.
- **Invalid product or vendor**: non-existent IDs in payload; ensure validation fails.
- **Quantity exceeds capacity**: expect validation error (no split behavior).
- **Multiple warehouses**: choose correct hub based on lowest total cost.

## Assertions to Add (Behavior Invariants)

- **Shipment count == hops**: number of shipments equals number of route legs used.
- **Sequence order is contiguous**: `sequence_index` runs 0..n-1 with no gaps.
- **All shipments link correctly**: each leg’s target matches next leg’s source.
- **Totals are consistent**: order total = sum(item totals) + sum(route costs).
- **Cash impact is correct**: game cash reduces by order total when order is accepted.
- **Status/state**: order starts in expected status (e.g., “pending” / “in_transit”).
- **Idempotency**: repeated same payload does not double-create shipments if system is expected to be idempotent.

## Structuring for Regression Test Readiness

- **Data provider approach**: define scenario payloads and expected outcomes to run through a single test method.
- **Scenario builder helpers**: extract setup into helpers (createVendorPath, createRoutes, createProductBundle) to avoid repetition.
- **Assertion helpers**: centralize shipment graph validation + cost calculation checks.
- **Explicit naming**: include scenario names in test output to aid debugging (e.g., “worst_case_long_chain”).
- **Keep deterministic**: avoid randomness; use fixed quantities/costs/route choices.

## Suggested Scenario Bundles (Quick Start)

1. **best_case_two_hop**
   - 2 legs, single product, small quantity, plenty of cash.
2. **average_case_four_hop_mixed_modes**
   - 4 legs, mixed transport, 2 products.
3. **worst_case_eight_hop_capacity_edge**
   - 8 legs, capacity equals quantity, tight cash buffer.
4. **edge_no_route**
   - No path from vendor to store.
5. **edge_inactive_route_mid_path**
   - Path exists but one leg inactive.
6. **edge_cycle_present**
   - Graph with loop; ensure chosen path is acyclic.

## Assumptions + Expected Outcomes (Aligned to Current Implementation)

1. **Route selection uses lowest total cost**  
   - Expect chosen path to minimize sum of `calculateCost(route)` (includes spikes).  
   - Avoid equal-cost ties in tests to keep results deterministic.

2. **Capacity overflow is a hard reject**  
   - If order quantity > min capacity along the path, expect validation error.  
   - No orders or shipments created.

3. **Insufficient cash is a hard reject**  
   - Expect validation error, no order created, no shipments created.  
   - Cash remains unchanged.

4. **Shipments are per order (per leg)**  
   - One shipment per route leg; no per-product shipments.

5. **Route cost is flat per leg (not per unit)**  
   - Logistics cost = sum(route_costs) across the chosen path.  
   - Order total = item total + logistics cost.

6. **Source equals target fails validation**  
   - Expect validation error, no order created, no shipments.

7. **Inactive legs are excluded from routing**  
   - Pathfinding only uses active routes.  
   - If no active path exists, expect validation error.

---

Next: convert these assumptions into scenario-specific expected values in the data provider.
