# Implementation Plan: Core Services & Dependency Injection

## Phase 1: Foundations (DTOs & Interfaces) [checkpoint: 74006eb]
- [x] **Task 1.0:** Install Prism Package (`composer require echolabsdev/prism`).
- [x] **Task 1.1:** Create `app/DTOs` directory and implement `InventoryContextDTO`.
- [x] **Task 1.2:** Implement `InventoryAdvisoryDTO`.
- [x] **Task 1.3:** Create `app/Interfaces` directory and define `AiProviderInterface`.
- [x] **Task 1.4:** Define `RestockStrategyInterface`.

## Phase 2: Core Math & Strategies
- [ ] **Task 2.1:** Implement `InventoryMathService` in `app/Services`.
    - Port logic from `resources/js/services/skuMath.ts` (if available) or implement standard formulas (EOQ, Safety Stock).
- [ ] **Task 2.2:** Implement `JustInTimeStrategy` class.
- [ ] **Task 2.3:** Implement `SafetyStockStrategy` class.

## Phase 3: Service Orchestration
- [ ] **Task 3.1:** Implement `InventoryManagementService`.
    - Inject `InventoryMathService`.
    - Implement `restock` method with DB transaction.
    - Implement `consume` method.
- [ ] **Task 3.2:** Implement `PrismAiService`.
    - Use Prism to generate text/structured output.
    - Implement `generateAdvisory` method.
    - Ensure it returns `InventoryAdvisoryDTO`.

## Phase 4: Wiring & Verification
- [ ] **Task 4.1:** Create `GameServiceProvider` (`php artisan make:provider GameServiceProvider`).
- [ ] **Task 4.2:** Register `GameServiceProvider` in `bootstrap/providers.php`.
- [ ] **Task 4.3:** Bind `AiProviderInterface` to `PrismAiService`.
- [ ] **Task 4.4:** Create a verification script `tests/verify_core_services.php` to instantiate services and run basic logic checks (as per project directive).
