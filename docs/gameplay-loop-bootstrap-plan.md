# Gameplay Loop Bootstrap Plan

**Created**: 2026-01-19  
**Status**: Draft  
**Purpose**: Make new games start with a realistic, engaging first 5-day loop by seeding inventory breadth, in-transit activity, and enforcing consistent user scoping across seeders/factories.

**Related**:
- `docs/guaranteed-spike-generation-plan.md` - spike seeding + daily spike guarantee (complements this plan)

---

## Problem Statement

The spike plan resolves “early days are uneventful” by ensuring spikes exist and are correctly user-scoped. However, a realistic gameplay loop still requires a credible baseline economy and logistics state at game start.

Current gaps that prevent a complete, engaging Day 1 → Day 5 loop:

1. **Insufficient initial inventory breadth**: Initial inventory is narrow (few SKUs, few locations), limiting meaningful tradeoffs (stockouts vs storage cost, perishables vs non-perishables, multi-location balancing).
2. **No seeded in-transit activity**: New games start with no deliveries/transfers in motion, so day-advance yields few immediate outcomes.
3. **Inconsistent user scoping in seeders/factories**: Core simulation ticks and costs are `user_id` scoped, but seeders/factories frequently create per-user records without a `user_id`, causing the game to “look populated” in some views but behave empty in the per-user simulation.

This plan defines the bootstrap state for “new game realism” and provides scoping rules so the seeded world and per-user state align with the simulation, costs, and events.

---

## Current State (Repository Observations)

### Seeders

- `database/seeders/DatabaseSeeder.php` creates 1 test user and calls:
  - `database/seeders/CoreGameStateSeeder.php` (vendors/products + a small inventory snapshot)
  - `database/seeders/GraphSeeder.php` (locations + routes graph)

### Where `user_id` matters today

User-scoped processing already exists in core loops/listeners:
- Simulation ticks filter by `user_id` for orders/transfers/spikes in `app/Services/SimulationService.php`
- Storage costs and perishable decay operate on `inventories.user_id` (`app/Listeners/ApplyStorageCosts.php`, `app/Listeners/DecayPerishables.php`)
- Inventory updates for delivered orders and completed transfers rely on events (`app/Listeners/UpdateInventory.php`)

### Known mismatch to account for

Transfers currently transition to `completed` in `SimulationService`, but **no `TransferCompleted` event is dispatched**, so inventory/metrics/alerts won’t update from transfers unless the completion event is emitted via a transition class or explicit dispatch.

---

## Scope

### In Scope

1. **Initial inventory breadth** (per-user): Seed enough inventory across multiple locations to enable early decisions and make costs/decay meaningful.
2. **Seeded pipeline activity** (per-user): Seed orders/transfers “in motion” so Days 2–4 include deliveries/completions.
3. **Seeder/factory scoping rules**: Define and enforce a consistent “global world vs per-user state” policy for test/dev seeding and test factories.

### Out of Scope (Track Separately)

- UI/controller scoping audits (some pages query without filtering by `user_id`)
- Economic balancing (prices, demand model, difficulty curves)
- Any new gameplay mechanics beyond bootstrapping a credible initial state

---

## Design Decisions (Proposed Defaults)

| Decision | Default |
|---|---|
| World data | Global shared topology/catalog (no `user_id`) |
| Player state | Per-user tables always seeded with `user_id` |
| Seeded locations with starting inventory | 2–3 locations (store + warehouse + optional second store) |
| Seeded SKUs | All “core” SKUs available at start, with intentional low-stock at one location |
| Seeded pipeline | 1–2 shipped orders + 1–2 in-transit transfers (deliver/complete Days 2–4) |
| Randomness | Avoid randomness in tests; allow limited randomness in local/dev seeders |

---

## User Scoping Rules (Seeders & Factories)

### 1) Global “World” Tables (no `user_id`)

These represent the shared map and catalog and should be created once (or idempotently):
- `locations`, `routes`, `vendors`, `products` (and pivots like vendor/product relationships)

### 2) Per-User “Game State” Tables (must have `user_id`)

These are player state and must be created for the target user:
- `game_states`, `inventories`, `orders`, `order_items`, `transfers`, `spike_events`, `alerts` (and similar player state tables)

**Rule**: even if the schema allows `user_id` to be nullable, gameplay bootstrapping must treat it as required.

### 3) Factories must support “attach to an existing user”

For per-user models, factories must make it easy to bind to an existing user without accidentally creating a new user per related record chain.

---

## Solution Architecture

### Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                    New Game Bootstrap Flow                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  WORLD INIT (GLOBAL)                 PLAYER INIT (PER-USER)      │
│  ──────────────────                 ─────────────────────────    │
│                                                                  │
│  Seed/Ensure World Graph             InitializeNewGame($user)     │
│  - locations/routes                  - ensure GameState           │
│  - vendors/products                  - seed starting inventory    │
│                                      - seed in-transit pipeline   │
│                                      - seed initial spikes (2–7)  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Integration with the spike plan

This plan assumes `InitializeNewGame` (introduced in `docs/guaranteed-spike-generation-plan.md`) becomes the single orchestration point for all per-user bootstrap steps, including spike seeding.

---

## Implementation Tasks

### Phase 1: Formalize scoping policy

1. Document and adopt the Global vs Per-User split above.
2. Ensure world seeders can be re-run without creating broken duplicates (idempotent where practical).

### Phase 2: Seed initial inventory breadth (per-user)

**Goal**: On Day 1, the player can make meaningful early choices:
- Stockouts vs storage cost (storage costs apply to `inventories.user_id`)
- Perishable decay management (decay applies to `inventories.user_id`)
- Multi-location balancing (transfers have value)

**Proposed template (example shape, not final numbers)**:
- Primary store: moderate stock of all core SKUs
- Warehouse: bulk non-perishables, limited perishables
- Optional secondary store: intentionally low stock on 1–2 SKUs to encourage early transfers

**Rules**:
- Every inventory row must include `user_id`.
- Use deterministic templates for tests; optionally add “small randomness” in local/dev seeding.

### Phase 3: Seed in-transit activity (per-user)

**Goal**: Days 2–4 have visible state changes without requiring the player to place orders immediately.

#### Task 3.1: Seed 1–2 shipped orders arriving soon

Create orders in shipped state with `delivery_day` in Days 2–4 and valid routing:
- Required fields: `user_id`, `vendor_id`, `location_id`, `route_id`, `delivery_day`, `status=shipped`
- Include order items across multiple SKUs when possible

This leverages existing `OrderDelivered` event propagation (state transition classes exist for orders).

#### Task 3.2: Seed 1–2 in-transit transfers arriving soon

Create transfers in `in_transit` with `delivery_day` in Days 2–4:
- Required fields: `user_id`, `source_location_id`, `target_location_id`, `product_id`, `quantity`, `delivery_day`, `status=in_transit`

#### Task 3.3: Ensure transfer completion has gameplay consequences

Bootstrap seeding should only treat transfers as “real” if completion updates inventory/alerts/metrics. Today, that requires one of:
- Add transfer transition classes that dispatch `TransferCompleted`, or
- Dispatch `TransferCompleted` explicitly when transfers complete in the simulation tick

Without this, seeded transfers will complete “silently” and won’t affect inventory.

### Phase 4: Restructure seeders (world vs player)

Goal: avoid mixing global world creation with per-user state.

Proposed direction:
- Keep world seeders focused on global tables (`locations`, `routes`, `vendors`, `products`)
- Create a per-user bootstrap seeder (or extend `DatabaseSeeder`) that:
  - creates/ensures the test user + game state
  - invokes `InitializeNewGame($user)` to seed per-user inventory, pipeline activity, and spikes

### Phase 5: Factory consistency improvements (for tests)

Goal: enable predictable multi-record setups without unintended user creation.

Examples of factory capabilities needed:
- Create an order bound to a specific user, location, route, and with items
- Create a transfer bound to a specific user and locations, with `in_transit` + `delivery_day`
- Create inventories for a specific user across multiple locations

### Phase 6: Testing strategy

Add targeted feature tests that validate bootstrap, separate from “manual setup” gameplay tests:

1. **Bootstrap test**: Asserts a new user bootstrap yields:
   - Inventory across 2–3 locations
   - Coverage across core SKUs (or a documented subset)
   - At least one incoming delivery (order and/or transfer) scheduled Days 2–4
   - Initial spikes scheduled Days 2–7 (delegated to the spike plan)
2. **5-day loop sanity test**: Advance time to Day 5 and assert:
   - At least one delivery/completion produced an inventory delta
   - Spikes occur during Days 2–5 (guaranteed spike plan)
   - No cross-user leakage when simulating multiple users

---

## File Summary (Planned)

| File | Action | Description |
|------|--------|-------------|
| `docs/gameplay-loop-bootstrap-plan.md` | Create | This document |
| `app/Actions/InitializeNewGame.php` | Modify | Extend to inventory + pipeline + spikes bootstrap |
| `database/seeders/*` | Modify/Create | Separate world vs per-user bootstrapping |
| `database/factories/*` | Modify | Consistent `user_id` attachment patterns |
| `tests/Feature/*` | Create | Bootstrap + 5-day loop tests |

---

## Execution Order

1. Finalize scoping policy and bootstrap defaults
2. Implement per-user bootstrap action changes
3. Update seeders to call the bootstrap action
4. Fix factories for predictable test setups
5. Add tests (bootstrap + 5-day loop)

---

## Success Criteria

- [ ] New games start with per-user inventory across multiple locations and core SKUs
- [ ] New games start with at least one pipeline item (order/transfer) completing by Day 4
- [ ] All per-user seeded rows include `user_id`
- [ ] Works cleanly with `docs/guaranteed-spike-generation-plan.md` (spikes Days 2–7, daily guarantee after Day 1)
- [ ] A Day 1 → Day 5 simulation produces visible state changes (deliveries + spikes) without requiring immediate player action

