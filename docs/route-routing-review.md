# Route Routing Review (Vendor Hub to Destination Store)

## Context
This note summarizes a targeted review of how the app determines routes between a vendor hub (ship-from location) and a destination store. The review was requested to clarify the current pathfinding behavior and whether the seeders (including `InitializeNewGame`) guarantee a valid route between those locations.

## Findings
- **High:** `CoreGameStateSeeder` creates a main store (`Moonshine Central`) that is not wired into the graph by `GraphSeeder`, so that destination can be unreachable from vendors/warehouses/hub routes.
  - Files: `database/seeders/CoreGameStateSeeder.php`, `database/seeders/GraphSeeder.php`
- **Medium:** The order UI only requests *direct* routes (exact `source_id` + `target_id`), but the seeded graph does not include direct vendor-to-store edges. This commonly yields zero available routes unless the source is a warehouse; hubs also are not selectable as a ship-from location in the order dialog.
  - Files: `resources/js/components/game/route-picker.tsx`, `resources/js/components/game/new-order-dialog.tsx`, `database/seeders/GraphSeeder.php`
- **Critical:** `InitializeNewGame` seeds a shipped order with incorrect destination logic (lines 95-110). It finds a vendor→warehouse route but uses the route's `target_id` (the warehouse) as the `location_id` for the order, delivering to the warehouse instead of a store. The code queries for a vendor→warehouse route but comments claim "Find a route from a vendor location to the store."
  - Files: `app/Actions/InitializeNewGame.php:95-110`
- **Low:** The order request validation does not enforce that `route_id` matches the selected source or destination location, so a mismatched route could be submitted if the UI is bypassed.
  - Files: `app/Http/Requests/StoreOrderRequest.php`

## How Routing Works Today
- **Direct-route lookup:** `/game/logistics/routes` returns only edges matching the exact `source_id` and `target_id`; no multi-hop pathfinding is used for orders.
  - Files: `app/Http/Controllers/LogisticsController.php`, `resources/js/components/game/route-picker.tsx`
- **Multi-hop pathfinding:** `/game/logistics/path` runs Dijkstra across active routes (costs adjusted by spikes) and returns the cheapest path; this is used by the transfer screen.
  - Files: `app/Services/LogisticsService.php`, `app/Http/Controllers/LogisticsController.php`, `resources/js/pages/game/transfers.tsx`

## Route Graph Topology (from GraphSeeder)
The seeded logistics graph creates the following connection pattern:

**Locations:**
- 3 vendor locations
- 2 warehouse locations  
- 5 store locations (from factory)
- 1 central transit hub
- 1 main store (`Moonshine Central`) from CoreGameStateSeeder (not wired into graph)

**Direct Routes:**
- Vendors → Warehouses (Truck, 2 days, $50) - 3×2 = 6 routes
- Warehouses → Stores (Truck, 1 day, $100) - 2×5 = 10 routes
- Stores → Stores (lateral, 3 days, $150) - 4 routes in a chain
- Vendors → Hub (Air, 1 day, $500) - 3 routes
- Hub → Stores (Air, 1 day, $500) - 5 routes

**Key Observations:**
- No direct vendor→store routes exist (multi-hop required: vendor→warehouse→store or vendor→hub→store)
- The main store `Moonshine Central` from CoreGameStateSeeder is isolated with no incoming routes
- Multi-hop paths from vendors to stores are guaranteed for the 5 stores created by GraphSeeder
- LogisticsService.findBestRoute() can find optimal paths through the graph, but it's not used by the order UI

## InitializeNewGame Bug Detail
The order seeding logic at lines 95-110 has a destination mismatch:

```php
$vendorLocation = Location::where('type', 'vendor')->first();
$route = null;
if ($vendorLocation) {
    $route = Route::where('source_id', $vendorLocation->id)
        ->where('is_active', true)
        ->first();  // Returns vendor→warehouse route
}

if ($route) {
    $order = Order::create([
        'location_id' => $route->target_id,  // BUG: This is the warehouse, not a store
        'route_id' => $route->id,
        // ...
    ]);
}
```

**Correct approach would use LogisticsService:**
```php
$logistics = app(LogisticsService::class);
$path = $logistics->findBestRoute($vendorLocation, $store);

if ($path && $path->isNotEmpty()) {
    Order::create([
        'location_id' => $store->id,  // Actual destination
        'route_id' => $path->first()->id,  // First leg of journey
        // ...
    ]);
}
```

## Seeder Guarantees (Answer)
- **No**: The current seeders do not guarantee a valid vendor-hub to destination-store route for *all* stores.
- The seeded graph (`GraphSeeder`) guarantees multi-hop paths only among the 5 stores it creates, via vendor→warehouse→store or vendor→hub→store routes
- The main store (`Moonshine Central`) from `CoreGameStateSeeder` is completely isolated - it has no incoming routes and is unreachable from any vendor
- `InitializeNewGame` has a critical bug where seeded orders are delivered to warehouses instead of stores, and silently fails if no route is found without logging
- No validation exists to ensure orders target reachable destinations

## Open Questions
- Should the order flow adopt multi-hop pathfinding (`/game/logistics/path`) rather than requiring a direct route, or should the seeders be updated to guarantee direct vendor-to-store routes?

## Recommended Fixes

**Priority 1 - Critical (InitializeNewGame):**
1. Fix order seeding destination logic (app/Actions/InitializeNewGame.php:95-110)
2. Use LogisticsService.findBestRoute() to find vendor→store paths
3. Add error logging when routes cannot be found

**Priority 2 - High (Isolated Main Store):**
1. Either wire `Moonshine Central` into the graph via routes from warehouses/hub
2. Or remove it and rely on the 5 stores from GraphSeeder
3. Update CoreGameStateSeeder to coordinate with GraphSeeder

**Priority 3 - Medium (Order UI):**
1. Either adopt multi-hop pathfinding for orders (like transfers)
2. Or seed direct vendor→store routes in GraphSeeder
3. Consider making the hub selectable as a ship-from location for air freight

**Priority 4 - Low (Validation):**
1. Add validation in StoreOrderRequest to enforce route_id matches source/destination
2. Consider adding a reachability check before allowing order submission
