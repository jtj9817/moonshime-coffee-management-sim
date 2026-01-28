# Multi-Hop Order Test Scenarios (Brainstorm)

Goal: expand `tests/Feature/MultiHopOrderTest.php` into a future-friendly regression suite that covers best/average/worst cases and edge conditions for multi-hop orders.

## Scenario Matrix (Best / Average / Worst)

| Scenario Type | Description | Primary Risk It Covers |
| --- | --- | --- |
| Best case | 2-hop chain with ample cash, capacity, active routes, single product | Happy path correctness (baseline regression) |
| Average case | 3–4 hops, mixed transport modes, moderate quantity, multiple products | Typical planning logic + cost/time aggregation |
| Worst case | 6–10 hops, tight cash, near-capacity routes, long transit times, multiple items | Performance limits + compounded costs + edge constraints |

## Concrete Scenario Ideas

### Best Case
- **Minimal viable multi-hop**: Vendor → Hub → Store with one product and small quantity.
- **Single product, single vendor**: ensures route selection + shipments are created in the correct sequence.
- **Simple cost model**: verify totals = item cost + sum(route costs) with exact rounding rules.

### Average Case
- **3–4 hops with mixed modes**: e.g., truck → rail → van → bike; assert the correct number of shipments and cost aggregation.
- **Multiple products in a single order**: verify per-item totals roll up into order total and shipments are still per-route (not per-product) unless design says otherwise.
- **Two possible routes** (one cheaper, one faster): if routing logic chooses based on cost/time, assert the chosen path matches the rules.
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
- **Inactive route in the middle**: path exists but includes inactive leg → ensure order is rejected or alternative route chosen.
- **Zero or negative capacity**: route capacity is 0 or negative → ensure rejection.
- **Cycle detection**: routes forming a loop (A→B→C→A) → ensure algorithm avoids infinite loops.
- **Duplicate routes with different costs**: deterministic selection (lowest cost / shortest time) verified.
- **Missing vendor location**: vendor has no associated location; ensure error handling is explicit.
- **Source equals target**: ordering from vendor location to same location; ensure either no shipments or defined error.
- **Rounding edge**: unit_price with 3+ decimals; verify rounding rules and exact totals.
- **Empty items list**: ensure validation fails.
- **Invalid product or vendor**: non-existent IDs in payload; ensure validation fails.
- **Quantity exceeds capacity**: verify either rejection or split behavior (if supported).
- **Multiple warehouses**: choose correct hub based on routing rules (shortest path, cost, or explicit preference).

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

## Open Questions (Clarify Before Implementation)

- How does routing choose between multiple possible paths (cost vs time vs fixed rules)?
- Should orders be split across routes when capacity is insufficient?
- What is the expected behavior when cash is insufficient: reject or allow negative?
- Are shipments created per order or per product? (affects shipment count assertions)
- Are route costs fixed or per-unit? (affects total calculations)

---

If you want, I can draft a test data provider template and helper methods next (no behavior changes yet).
