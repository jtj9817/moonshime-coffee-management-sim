# Track Specification: Core Services & Dependency Injection

## Overview
This track focuses on implementing the core business logic services and the dependency injection infrastructure required for the Moonshine Coffee Management Sim. We will move away from placing logic in Controllers and instead use strictly typed Services, DTOs, and Interfaces.

## Goals
1.  **Type Safety:** Establish DTOs for data transfer between services.
2.  **Decoupling:** Implement Interfaces for external dependencies (AI) and variable logic (Restock Strategies).
3.  **Core Logic:** Port and refine inventory mathematics and management logic from the frontend to the Laravel backend.
4.  **DI Configuration:** Bind all services and interfaces in a dedicated Service Provider.

## Scope

### 1. Data Transfer Objects (DTOs)
Located in `app/DTOs/`.
*   **`InventoryContextDTO`**: Encapsulates the current state of inventory (levels, sales velocity) to be sent to the AI.
*   **`InventoryAdvisoryDTO`**: Encapsulates the AI's recommendations (restock amounts, reasoning).

### 2. Interfaces
Located in `app/Interfaces/`.
*   **`AiProviderInterface`**: Defines the contract for AI services.
    *   Method: `generateAdvisory(InventoryContextDTO $context): InventoryAdvisoryDTO`
*   **`RestockStrategyInterface`**: Defines the contract for inventory replenishment logic.
    *   Method: `calculateReorderAmount(Inventory $inventory): int`

### 3. Services
Located in `app/Services/`.

*   **`InventoryMathService`** (Stateless)
    *   Pure calculation methods (EOQ, Safety Stock, Reorder Point).
    *   Must use Laravel Collections for data processing.

*   **`InventoryManagementService`** (Stateful/Orchestrator)
    *   Dependencies: `InventoryMathService`, `RestockStrategyInterface`.
    *   Handles `restock`, `consume`, and `waste` operations.
    *   Uses DB Transactions.

*   **`GeminiService`**
    *   Implements `AiProviderInterface`.
    *   Connects to Google Gemini API (mocked for now or using real API if key available).

### 4. Strategies
Located in `app/Services/Strategies/`.
*   **`JustInTimeStrategy`**: Minimizes stock holding.
*   **`SafetyStockStrategy`**: Prioritizes avoiding stockouts.

### 5. Service Provider
*   **`GameServiceProvider`**: Registers bindings.

## References
*   `docs/moonshine-laravel-integration-plan.md` (Step 3)
