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

## Concrete Scenario Data Table (Filled Values)

All costs are **per leg**. Path selection is **lowest total cost**. Avoid equal-cost ties.

| Scenario | Cash | Locations | Routes (from→to, cost, days, capacity, active) | Items (product, qty, unit_price) | Expected Path | Expected Totals |
| --- | --- | --- | --- | --- | --- | --- |
| best_case_two_hop | 100.00 | vendor_loc, hub_a, store | vendor_loc→hub_a, 1.00, 2, 200, true; hub_a→store, 2.00, 1, 200, true | coffee, 100, 0.10 | vendor_loc→hub_a→store | items 10.00, logistics 3.00, total 13.00 |
| average_case_four_hop_mixed_modes | 100.00 | vendor_loc, hub_a, hub_b, hub_c, store, alt_a, alt_b | vendor_loc→hub_a, 1.00, 2, 200, true; hub_a→hub_b, 1.50, 2, 200, true; hub_b→hub_c, 1.00, 1, 200, true; hub_c→store, 0.50, 1, 200, true; vendor_loc→alt_a, 2.00, 1, 200, true; alt_a→alt_b, 2.00, 1, 200, true; alt_b→store, 1.00, 1, 200, true | coffee, 20, 1.25; tea, 30, 0.50 | vendor_loc→hub_a→hub_b→hub_c→store | items 40.00, logistics 4.00, total 44.00 |
| worst_case_eight_hop_capacity_edge | 15.00 | vendor_loc, h1, h2, h3, h4, h5, h6, h7, store | vendor_loc→h1, 0.50, 1, 50, true; h1→h2, 0.50, 1, 50, true; h2→h3, 0.50, 1, 50, true; h3→h4, 0.50, 1, 50, true; h4→h5, 0.50, 1, 50, true; h5→h6, 0.50, 1, 50, true; h6→h7, 0.50, 1, 50, true; h7→store, 0.50, 1, 50, true | coffee, 50, 0.20 | vendor_loc→h1→h2→h3→h4→h5→h6→h7→store | items 10.00, logistics 4.00, total 14.00 |
| edge_no_route | 100.00 | vendor_loc, store | none | coffee, 10, 1.00 | none | validation error on location_id |
| edge_inactive_route_mid_path | 100.00 | vendor_loc, hub_a, hub_b, store, alt_a | vendor_loc→hub_a, 0.50, 1, 200, true; hub_a→hub_b, 0.50, 1, 200, **false**; hub_b→store, 0.50, 1, 200, true; vendor_loc→alt_a, 1.00, 1, 200, true; alt_a→store, 1.00, 1, 200, true | coffee, 10, 1.00 | vendor_loc→alt_a→store | items 10.00, logistics 2.00, total 12.00 |
| edge_cycle_present | 100.00 | vendor_loc, hub_a, hub_b, store | vendor_loc→hub_a, 0.20, 1, 200, true; hub_a→hub_b, 0.20, 1, 200, true; hub_b→vendor_loc, 0.20, 1, 200, true; vendor_loc→store, 1.00, 1, 200, true | coffee, 10, 1.00 | vendor_loc→store | items 10.00, logistics 1.00, total 11.00 |
| edge_capacity_exceeded | 100.00 | vendor_loc, hub_a, store | vendor_loc→hub_a, 1.00, 1, 50, true; hub_a→store, 1.00, 1, 50, true | coffee, 60, 0.50 | none | validation error on items (capacity) |
| edge_insufficient_cash | 19.99 | vendor_loc, hub_a, store | vendor_loc→hub_a, 1.00, 1, 200, true; hub_a→store, 1.00, 1, 200, true | coffee, 10, 1.80 | vendor_loc→hub_a→store | items 18.00, logistics 2.00, total 20.00 (reject) |
| edge_source_equals_target | 100.00 | vendor_loc | none | coffee, 10, 1.00 | none | validation error on location_id |

## Assertion Mapping Notes (For MultiHopOrderTest)

- **Route selection**: assert the chosen path is the lowest total cost; avoid equal-cost alternatives in fixtures.  
- **Totals**: `order.total_cost` = sum(items) + sum(route costs), rounded to 2 decimals.  
- **Shipments**: exactly one shipment per leg; `sequence_index` is 0..n-1 with no gaps.  
- **Continuity**: each shipment’s target matches the next shipment’s source.  
- **Validation failures**: assert session errors and that no `orders` or `shipments` rows were created; cash unchanged.  
- **Capacity failures**: expect error on `items` when quantity > min capacity along the chosen path.  
- **Insufficient cash**: expect error on `total` when cash < computed total.  
- **Inactive legs**: ensure inactive routes are ignored; test asserts fallback to next cheapest active path or failure if none exist.  
- **Vendor/source mapping**: pass `source_location_id` explicitly in test payload to avoid vendor/location mismatch.  

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
