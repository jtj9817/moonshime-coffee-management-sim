# Playwright E2E Test Report

## Overview
Comprehensive E2E tests have been implemented using Playwright to verify Gameplay Features as per `docs/gameplay-features-implementation-spec.md`.

**Test Suite:** `tests/e2e/`
**Setup:** SQLite database (`database/e2e.sqlite`) with `.env.testing`.
**Authentication:** All tests run with a fresh registered user.

## Test Specs Created

1.  **Authentication (`auth.spec.ts`)**: Verifies registration and initial game state (Cash, Day 1).
2.  **Phase 1: Visibility & Consequences (`phase1.spec.ts`)**:
    *   Demand Forecasting (SKU Detail).
    *   Daily Summary (Dashboard after day advance).
    *   Financial Granularity (Analytics P&L).
    *   Pricing Strategy (SKU Detail).
3.  **Phase 2: Core Engagement (`phase2.spec.ts`)**:
    *   Quest System (Ordering flow).
    *   Active Spike Resolution (Spike history).
4.  **Phase 3: Strategic Planning (`phase3.spec.ts`)**:
    *   Scenario Calculator (Ordering page).
    *   Bulk Order Scheduler (New Order Dialog).

## Test Results & Issues Identified

### 1. Authentication
*   **Status:** ✅ Passed
*   **Notes:** Initial game state (Cash $10,000, Day 1) is correct.

### 2. Phase 1: Visibility & Consequences
*   **Status:** ❌ Failed
*   **Issue 1 (Critical): Day Advancement Failure**
    *   **Description:** Clicking "Next Day" button does not result in the game day advancing to Day 2 in the UI.
    *   **Impact:** Blocks testing of Daily Summary, Financial Granularity (P&L), and any multi-day mechanics.
    *   **Possible Cause:** Backend error during `advance-day` action, database locking, or UI state not updating.
*   **Issue 2: Location Navigation**
    *   **Description:** Tests timeout when attempting to click Location Cards on the dashboard.
    *   **Possible Cause:** UI overlay (Toasts) blocking clicks, or component rendering delay.

### 3. Phase 2: Core Engagement
*   **Status:** ❌ Failed
*   **Issue 3: Quest System / Ordering UI Ambiguity**
    *   **Description:** "Select vendor" dropdown trigger matches multiple elements ("Select vendor" and "Select vendor first"), causing Strict Mode violations in Playwright.
    *   **Fix Required:** Add unique test IDs or `aria-labels` to `NewOrderDialog` components, or refine test selectors.
*   **Issue 4: Active Spike Resolution**
    *   **Description:** Blocked by Day Advancement Failure (Issue 1). Spikes are not triggered because day does not advance.

### 4. Phase 3: Strategic Planning
*   **Status:** ❌ Failed
*   **Issue 5: Scenario Calculator Selector Ambiguity**
    *   **Description:** "Day X" text matches both the Day Counter in the header and the Calculator output.
    *   **Fix Required:** Scope selectors to the Calculator card.
*   **Issue 6: Bulk Order Scheduler**
    *   **Description:** Failed due to Ordering UI Ambiguity (Issue 3).

## Recommendations

1.  **Fix Day Advancement:** Investigate why `advance-day` fails or doesn't update UI in the test environment.
2.  **Add Test IDs:** Add `data-testid` attributes to critical UI elements (e.g., `NewOrderDialog` triggers, `AdvanceDayButton`, `LocationCard`) to make tests more robust and less reliant on text/accessibility roles that may be ambiguous.
3.  **Handle Toasts:** Improve test resilience against Flash Toasts blocking UI elements (already partially addressed with `{ force: true }`, but better handling needed).

## Running Tests
To run the tests locally:
```bash
npx playwright test
```
To run specific file:
```bash
npx playwright test tests/e2e/phase1.spec.ts
```
