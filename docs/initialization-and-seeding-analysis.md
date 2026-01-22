# Initialization and Seeding Logic Analysis

## Context
This document outlines the findings from an analysis of the `App\Actions\InitializeNewGame` action and the `Database\Seeders\CoreGameStateSeeder`. The goal was to identify configuration dependencies, hard-coded logic, and potential failure modes that could impact the reliability of game initialization and the new user experience.

## `App\Actions\InitializeNewGame` Analysis

This action is responsible for setting up the per-user game state, including initial inventory, pipeline activity (orders/transfers), and random events (spikes).

### 1. Configuration & Environment Requirements
To run successfully, the application must be in the following state:

*   **Service Container Bindings:**
    *   `App\Services\LogisticsService`: Required for route calculation.
    *   `App\Services\OrderService`: Required for order creation.
    *   `Database\Seeders\SpikeSeeder`: Required to seed initial game events.

*   **Database Pre-requisites (Seed Data):**
    *   **Locations:** 
        *   At least one **Store** (`type='store'`). The first found is treated as "Primary".
        *   At least one **Warehouse** (`type='warehouse'`).
        *   At least one **Vendor** (`type='vendor'`).
    *   **Products:** At least one product must exist. The logic specifically checks the `is_perishable` boolean attribute.
    *   **Routes:** Active routes (`Route` model) must exist between the Vendor and the Store for the `LogisticsService` to find a valid path.
    *   **Vendors:** A `Vendor` record must exist.

### 2. Hard-Coded Values
The action contains several hard-coded values that define the initial game difficulty and state.

*   **Game State:**
    *   Starting Cash: `$10,000.00`
    *   Starting XP: `0`
    *   Starting Day: `1`

*   **Initial Inventory Levels:**
    *   **Primary Store:** Perishable (30), Non-Perishable (80)
    *   **Warehouse:** Perishable (20), Non-Perishable (200)
    *   **Secondary Stores:** Perishable (10), Non-Perishable (25)

*   **Scheduled Pipeline Activity:**
    *   **Day 2 Arrival:** Transfer of 25 units (Warehouse to Store).
    *   **Day 3 Arrival:** Vendor order of 50 units (Cost: $1.00/unit).
    *   **Day 4 Arrival:** Transfer of 15 units of a second product.

*   **Random Event Generation:**
    *   **Event Count:** 3 to 5 events.
    *   **Event Window:** Days 2-7.
    *   **Constraints:** Max 2 active spikes; 2-day cooldown per type.

### 3. Failure Modes

| Failure Type | Cause | Consequence |
| :--- | :--- | :--- |
| **Silent Failure** | Missing Locations (`store`/`warehouse`) or Products in DB. | Game initializes with `$10,000` but **zero inventory** and **no pipeline activity**. No error is thrown. |
| **Logic Failure** | No active `Route` between Vendor and Store. | `LogisticsService` returns null. The "Day 3" initial order is **never created**. |
| **Data Corruption** | Script crash (e.g., DB timeout) followed by a retry. | **Duplicate Data:** `Transfer::create` and `OrderService::createOrder` are not idempotent. A retry results in duplicate transfers and orders (double stock). |
| **Integrity Risk** | Exception during `SpikeSeeder` execution. | **Partial State:** Logic is not wrapped in a transaction. User could exist with inventory but no spike events. |

---

## `Database\Seeders\CoreGameStateSeeder` Analysis

This seeder populates the global "world" data (Products, Vendors) based on the `config/game_data.php` configuration.

**Configuration Source:** `/config/game_data.php` (hard-coded definitions mirroring `resources/js/constants.ts`)

**Hard-Coded World Data:**
- 12 products across 11 categories (Beans, Milk, Cups, Syrup, Tea, Sugar, Cleaning, Seasonal, Food, Sauce, Pastry)
- 4 vendors (BeanCo Global, RapidSupplies, Dairy Direct, ValueBulk) with reliability scores and product category assignments

**Note:** The configuration file itself is hard-coded—there is no separate config for world topology, logistics costs, or transit times.

### 1. Failure Modes

| Failure Type | Cause | Consequence |
| :--- | :--- | :--- |
| **Silent "Empty World"** | `config/game_data.php` is missing or has empty arrays. | Seeder runs successfully (0 operations). `InitializeNewGame` subsequently fails silently (see above), resulting in a dead game world. |
| **Domain Violation** | Typo in `vendors.categories` vs `products.category` string matching. | **Vendor-Product Mismatch:** Vendors are created but have **no products attached**. `InitializeNewGame` may blindly order a product from a vendor that doesn't technically sell it, or fail if no valid vendor-product pair is found. |
| **Integration Failure** | `GraphSeeder` fails or creates disconnected graph. | `CoreGameStateSeeder` creates the *business* entities, but if the *logistics* entities (Locations/Routes) are missing/disconnected, `InitializeNewGame` order generation fails. |

---

## `Database\Seeders\GraphSeeder` Analysis

This seeder creates the logistics network (Locations and Routes) with **no external configuration**—all values are hard-coded in the seeder file.

**Hard-Coded Topology:**
- **3 vendors** (type='vendor')
- **2 warehouses** (type='warehouse')
- **3 hubs** (type='hub') - for multi-path diversity
- **6 stores** (1 main store "Moonshine Central" + 5 additional)

**Hard-Coded Routes:**
| Connection | Transport | Cost | Transit Days |
|-----------|-----------|------|--------------|
| Vendor → Warehouse | Truck | $0.50 | 2 days |
| Warehouse → Store | Truck | $1.00 | 1 day |
| Store → Store (chain) | Truck | $1.50 | 3 days |
| Vendor → Hub | Air | $5.00 | 1 day |
| Hub → Store | Air | $5.00 | 1 day |

**No Configuration File Exists:** Unlike products/vendors, there is no `config/graph.php` or similar. All topology and pricing is embedded directly in the seeder.

### 1. Failure Modes

| Failure Type | Cause | Consequence |
| :--- | :--- | :--- |
| **Incomplete Graph** | Seeder fails mid-execution (e.g., Location creation succeeds, Route creation fails). | Partial logistics network exists. Some stores/hubs may be unreachable, causing `InitializeNewGame` order/transfer generation to fail silently. |
| **No Idempotency** | Running seeder multiple times. | Duplicate locations and routes created (names may be unique, but graph complexity grows exponentially). |

### 2. Recommendations for Improvement
*   **Externalize Configuration:** Create `config/graph.php` with topology definitions (location counts, route costs) to enable easier tuning.
*   **Idempotency Checks:** Use `Location::firstOrCreate()` and check for existing routes before creating to support safe re-seeding.

---

## `Database\Seeders\InventorySeeder` Analysis

Seeds global (non-user-specific) inventory for all store-product combinations using Faker-generated random values.

**Behavior:**
- Every store gets every product
- Quantity: 50-200 units (random)
- Uses `Inventory::updateOrCreate()` for idempotency

**Note:** This is distinct from `InitializeNewGame`, which creates user-specific inventory with hard-coded levels.

---

## Seeding Orchestration

The complete initialization chain is orchestrated by `DatabaseSeeder.php`:

```
DatabaseSeeder::run()
├── CoreGameStateSeeder (Products, Vendors from config/game_data.php)
├── GraphSeeder (Locations, Routes - hard-coded)
├── InventorySeeder (Global inventory - Faker random)
└── InitializeNewGame->handle(User)
    ├── seedInitialInventory (User-specific, hard-coded levels)
    ├── seedPipelineActivity (User-specific orders/transfers)
    └── SpikeSeeder->seedInitialSpikes (3-5 spikes, days 2-7)
```

**Critical Dependency:** `InitializeNewGame` depends on `CoreGameStateSeeder` and `GraphSeeder` having run first. ~~If they haven't, the action returns early without errors (silent failure).~~ **Now throws explicit `RuntimeException` with descriptive message.**

---

## Environment Independence

**None of the seeders or `InitializeNewGame` check `APP_ENV`.**

- No `env()` or `app()->environment()` calls exist in any seeder or initialization code
- All logic runs identically across `local`, `testing`, `staging`, and `production` environments
- `SpikeSeeder::run()` has a comment "Local/dev seeding only" but the implementation only checks for `GameState` existence, not the environment

**Implication:** Seeding behavior cannot be customized per environment without code changes.

---

## Improvements Implemented

The following improvements have been implemented to address the failure modes documented above:

### ✅ Transactions
- `InitializeNewGame::handle()` is now wrapped in `DB::transaction()` to prevent partial state on failure

### ✅ Validation with Explicit Errors
- All seeders validate configuration and dependencies before processing
- `InitializeNewGame` throws `RuntimeException` with descriptive messages when:
  - No stores found
  - No warehouse found
  - No products found
  - No vendor found

### ✅ Idempotency
- `InitializeNewGame` uses `firstOrCreate` for inventory and transfers
- Checks for existing user inventory/transfers before seeding
- `GraphSeeder` logs warnings when locations already exist

### ✅ Comprehensive Logging
- New `game-initialization` log channel in `config/logging.php`
- Daily rotating logs at `storage/logs/game-init-{date}.log`
- All seeders log:
  - Start/completion with timing
  - Entity counts (products, vendors, locations, routes)
  - Warnings for missing dependencies or category mismatches
  - Errors with full stack traces
- `InitializeNewGame` logs:
  - User ID and email at start
  - GameState creation details
  - Inventory seeding progress
  - Transfer/order creation details
  - Warnings when no route found

### ✅ Production Debugging (Laravel Forge)
- Logs accessible via Forge dashboard at Site → Logs
- SSH access: `tail -f storage/logs/game-init-$(date +%Y-%m-%d).log`
- 14-day log retention (configurable via `LOG_DAILY_DAYS`)

---

## Remaining Recommendations

*   **Strict Typing:** Replace string-based category matching in seeders with Enums or Constants to prevent configuration typos.

