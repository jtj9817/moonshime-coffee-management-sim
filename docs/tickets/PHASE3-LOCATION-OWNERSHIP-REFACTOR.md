# Ticket: Refactor and Optimize Location Ownership Logic

**Priority:** High
**Status:** Open
**Assignee:** Unassigned
**Created:** 2026-02-09
**Related Commit:** e1082b7

## Description
The implementation of "strict location ownership" in commit e1082b7 introduced significant logic duplication and side effects in the request lifecycle. The synchronization logic is repeated across four different files, and database writes are occurring in read-only middleware and validation classes.

## Critical Issues

### 1. Code Duplication & Maintenance Risk
**Severity:** High
**Location:**
- `app/Actions/InitializeNewGame.php`
- `app/Http/Controllers/GameController.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Http/Requests/StoreOrderRequest.php`

The method `ensureLocationOwnership` (or `syncLocationOwnership`) is copy-pasted in all these files. Any change to the logic requires updating four locations.

### 2. Side Effects in Read/Validation Layers
**Severity:** Medium
**Location:** `HandleInertiaRequests.php`, `StoreOrderRequest.php`
Database writes (`insertOrIgnore`) are being triggered during:
- Every page load (Middleware)
- Form validation (Request object)
This violates separation of concerns and can lead to performance degradation.

### 3. Flawed Synchronization Logic
**Severity:** High
The current implementation checks `if ($alreadyOwned) { return; }`. This means if a user has *any* entry in `user_locations`, the sync is skipped entirely. If a user gains access to a *new* location later in the game, this logic will fail to grant them access.

### 4. Runtime Schema Checks
**Severity:** Low
`Schema::hasTable('user_locations')` is running in hot code paths. This check should not be necessary in production code once migrations are run.

## Proposed Resolution

### Refactoring Tasks

- [ ] **Centralize Logic:** Move the location synchronization logic into the `User` model (e.g., `public function syncLocations(): void`) or a dedicated service.
- [ ] **Remove Side Effects:**
    - Remove calls to `ensureLocationOwnership` from `HandleInertiaRequests.php`.
    - Remove calls to `ensureLocationOwnership` from `StoreOrderRequest.php`.
    - Rely on the migration for backfilling existing users and `InitializeNewGame` for new users.
- [ ] **Fix Sync Logic:** Update the centralized method to use `syncWithoutDetaching` or `insertOrIgnore` without the early `if ($exists)` return, ensuring new locations are picked up.
- [ ] **Optimize Validation:** In `StoreOrderRequest.php`, replace raw `UserLocation` queries with the `User::locations()` relationship check.

### Example Implementation (User Model)

```php
public function syncLocations(): void
{
    $inventoryLocationIds = \App\Models\Inventory::query()
        ->where('user_id', $this->id)
        ->distinct()
        ->pluck('location_id');

    $vendorLocationIds = \App\Models\Location::query()
        ->where('type', 'vendor')
        ->pluck('id');

    $locationIds = $inventoryLocationIds
        ->merge($vendorLocationIds)
        ->unique();

    // Efficient idempotent sync
    $this->locations()->syncWithoutDetaching($locationIds);
}
```
