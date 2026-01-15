# Moonshine Supply Chain - Laravel Migration Plan

## 1. Project Architecture & Setup

### 1.1 Technology Stack
- **Backend:** Laravel 11
- **Frontend:** React 19 + TypeScript (via Inertia.js)
- **Styling:** Tailwind CSS (Existing)
- **Database:** PostgreSQL (Preferred) or SQLite (Dev)
- **Real-time:** Laravel Reverb (WebSockets) for dashboard updates
- **Package Manager:** pnpm

### 1.2 Directory Structure Map
| Current (React Only) | Target (Laravel + Inertia) |
|----------------------|----------------------------|
| `components/*` | `resources/js/Components/*` |
| `components/Layout.tsx` | `resources/js/Layouts/AppLayout.tsx` |
| `App.tsx` (Routes) | `routes/web.php` + `resources/js/Pages/*` |
| `services/geminiService.ts` | `app/Services/GeminiService.php` |
| `services/skuMath.ts` | `app/Services/InventoryMathService.php` |
| `types.ts` | `app/Models/*` + `resources/js/types/index.d.ts` |
| `assets/*` | `public/assets/*` or `resources/images/*` |

### 1.3 Design Patterns & Architectural Standards
To ensure maintainability and scalability, the backend will strictly adhere to the following patterns:

#### Dependency Injection (DI) & Service Providers
- **Strict DI:** Controllers will **never** contain business logic or instantiate services directly. All logic classes (Services, Repositories) must be injected via the constructor.
- **Service Binding:** A dedicated `GameServiceProvider` will be used to bind interfaces to implementations.
  - *Example:* Bind `AiProviderInterface` to `GeminiService` (prod) or `MockAiService` (testing).
- **Singletons:** Global game contexts (like the current `GameState` cache) will be registered as Singletons within the Service Container.

#### Observer & Pub-Sub Pattern (Events)
- We will decouple business actions from side effects using Laravel's **Event/Listener** system.
- **Workflow:**
  1. **Action:** `OrderService` creates an order.
  2. **Event:** Fires `OrderPlaced` event.
  3. **Listeners:**
     - `DeductCashListener`: Updates `GameState` cash.
     - `NotifyWarehouseListener`: Creates a warehouse alert.
     - `UpdateMetricsListener`: Recalculates vendor reliability score.

#### Active Record & Data Transformation
- **Active Record:** We will use Eloquent Models for database interactions. Models will contain domain-specific methods (e.g., `Product::isLowStock()`) but complex orchestration belongs in Services.
- **Collections:** Data shaping for the frontend (Inertia props) will utilize **Laravel Collections** extensively.
  - Use `map`, `filter`, `reduce`, `pluck`, and `groupBy` to transform database results into UI-ready structures without mutating the original data.
  - *Rule:* Avoid `foreach` loops for data transformation; prefer functional collection chains.

#### State Pattern (Finite State Machine)
- **Context:** Managing complex lifecycles for `Order` and `Transfer` models.
- **Implementation:** Use `spatie/laravel-model-states` to manage transitions.
- **Benefit:** Enforces valid transitions (e.g., `Draft` -> `Pending` -> `Shipped`) and encapsulates transition logic (validation, side effects) within dedicated State classes.
  - *Example:* `$order->state->transitionTo(Shipped::class)` ensures inventory is checked before shipping.

#### Strategy Pattern
- **Context:** Handling variable logic for `Policy` settings (e.g., auto-replenishment rules).
- **Implementation:** Define interfaces like `RestockStrategyInterface` with implementations like `JustInTimeStrategy` or `SafetyStockStrategy`.
- **Benefit:** Avoids massive `if/else` chains in services. The `InventoryManagementService` delegates logic to the strategy selected by the user's active Policy.

#### Factory Pattern
- **Context:** Generating random simulation events ("Chaos Monkey").
- **Implementation:** A `SpikeEventFactory` class handles weighted probabilities and instantiation of different event types (e.g., `Heatwave`, `Strike`).
- **Benefit:** Centralizes the complexity of random generation and object creation, keeping the simulation loop clean.

#### Data Transfer Objects (DTOs)
- **Context:** Passing structured data between services, especially for AI responses and Reporting.
- **Implementation:** Use `readonly` PHP classes (e.g., `InventoryAdvisoryData`) instead of associative arrays.
- **Benefit:** Provides type safety, auto-completion, and strictly defined data structures across boundaries.
  - *Usage:* `GeminiService`, `AnalyticsService`, and Controller responses.

### 1.4 Initial Setup Commands
1. **Initialize Laravel:** `composer create-project laravel/laravel moonshine-backend`
2. **Install Inertia:**
   - Composer: `composer require inertiajs/inertia-laravel`
   - PNPM: `pnpm add @inertiajs/react`
3. **Install State Package:** `composer require spatie/laravel-model-states`
4. **Configure Vite:** Update `vite.config.js` to include `resources/js/app.tsx`.
5. **Dependencies:** `pnpm add lucide-react recharts clsx tailwind-merge`.

---

## 2. Database Schema & Domain Modeling

We will replace `types.ts` interfaces with robust Eloquent models.

### 2.1 Core Domain Models

#### `Location` (Stores)
- **Table:** `locations`
- **Attributes:** `id` (uuid), `name`, `address`, `max_storage`
- **Relationships:** `hasMany(Inventory)`, `hasMany(Order)`

#### `Vendor` (Suppliers)
- **Table:** `vendors`
- **Attributes:** `id` (uuid), `name`, `reliability_score`, `metrics` (json)
- **Relationships:** `hasMany(Order)`, `belongsToMany(Product)`

#### `Product` (Items)
- **Table:** `products`
- **Attributes:** `id` (uuid), `name`, `category`, `is_perishable`, `storage_cost`
- **Relationships:** `hasMany(Inventory)`

#### `Inventory` (Stock)
- **Table:** `inventories`
- **Attributes:** `id` (uuid), `location_id`, `product_id`, `quantity`, `last_restocked_at`
- **Relationships:** `belongsTo(Location)`, `belongsTo(Product)`
- **Observers:** Use an `InventoryObserver` to monitor `updated` events. If quantity drops below threshold, fire `LowStockDetected` event.

#### `Order` (Procurement)
- **Table:** `orders`
- **Attributes:** `id` (uuid), `vendor_id`, `status` (managed by State Machine), `total_cost`, `delivery_date`
- **Relationships:** `hasMany(OrderItem)`
- **States:** `Draft`, `Pending`, `Shipped`, `Delivered`, `Cancelled`

#### `Transfer` (Internal Logistics)
- **Table:** `transfers`
- **Attributes:** `id` (uuid), `source_location_id`, `target_location_id`, `status` (managed by State Machine)

### 2.2 Operational & Gamification Models
- **`Alert`**: System notifications.
- **`GameState`**: Singleton user state (cash, xp, day).
- **`SpikeEvent`**: Active chaos events.
- **`Policy`**: User-defined strategy settings.

---

## 3. Backend Logic & Services (Refined)

### 3.1 Inventory & Math Services
- **`InventoryMathService` (Helper):**
  - **Type:** Stateless Service.
  - **Responsibility:** Pure calculation methods (EOQ, Safety Stock, Reorder Point).
  - **Implementation:** Will use `Collection` methods to reduce historical usage data into average daily demand.
- **`InventoryManagementService`:**
  - **Dependencies:** `InventoryMathService`, `RestockStrategyInterface`.
  - **Responsibility:** Handles logic for `restock`, `consume`, and `waste`.
  - **Pattern:** Uses **Strategy Pattern** to determine reorder amounts based on the user's active `Policy`. Uses **DB Transactions** for atomicity.

### 3.2 AI Integration
- **Interface:** `AiProviderInterface`
  - Method: `generateAdvisory(InventoryContextDTO $context): InventoryAdvisoryDTO`
- **Implementation:** `GeminiService` implements `AiProviderInterface`.
- **Usage:** Injected into `AiController`. Returns a strictly typed DTO to ensure the frontend receives consistent data structures.

### 3.3 Simulation Engine (The "Game Loop")
- **`SimulationService`:**
  - **Responsibility:** Advances the game time (Day/Hour).
  - **Pattern:** **Observer**. When `advanceTime()` is called, it fires `TimeAdvanced` event.
  - **Listeners for `TimeAdvanced`:**
    - `DecayPerishables`: Reduces quality/quantity of perishable stock.
    - `ProcessDeliveries`: Checks if orders should arrive.
    - `GenerateRandomSpikes`: Uses **`SpikeEventFactory`** to generate "Chaos Monkey" logic based on weighted probabilities.

### 3.4 Transfer & Order Logic
- **`OrderService` & `TransferService`:**
  - **Responsibility:** Managing lifecycle transitions.
  - **Pattern:** **State Pattern**.
    - *Example:* `$order->state->transitionTo(Shipped::class)`. The `Shipped` state class handles the side effect of decrementing vendor stock or starting the shipping timer.

---

## 4. Frontend Architecture (Inertia.js + React)

### 4.1 Page Composition & Persistent Layouts
- **Concept:** Inertia pages act as the view layer. We will use **Persistent Layouts** to prevent full page re-renders during navigation, preserving the state of the Sidebar, Header, and active Game Timer.
- **Structure:**
  - `resources/js/Layouts/GameLayout.tsx`: The main wrapper containing the Navigation and "Heads Up Display" (Cash/Day/XP).
  - `resources/js/Pages/*`: These components correspond 1:1 with Laravel Controller methods (e.g., `Inventory/Index.tsx`, `Orders/Create.tsx`).

### 4.2 Data Flow & Global State
- **Shared Props (`HandleInertiaRequests`):**
  - Instead of a client-side store (Redux/Context), we will inject global game state via the Inertia Middleware.
  - **Payload:** `auth.user`, `game.stats` (Cash, Day, Reputation), and `flash` messages.
  - **Access:** Components use `usePage<PageProps>()` to read this global state anywhere without prop drilling.
- **Partial Reloads:**
  - For polling components (like `SpikeMonitor` or `RealtimeAnalytics`), we will use `router.reload({ only: ['spikes'] })` to refresh specific data points without reloading the entire page content.

### 4.3 Form Handling & Mutations
- **Inertia `useForm`:**
  - All data mutations (Placing Orders, Updating Policy) will use the `useForm` hook.
  - **Benefits:** Automatic mapping of Laravel server-side validation errors (`$errors`) to UI fields, and built-in `processing` states for buttons.
- **Optimistic UI:**
  - For "Instant" feedback actions (e.g., "Quick Buy"), we will manually update the local UI state immediately, then fire the Inertia request in the background, reverting changes only if the server returns an error.

### 4.4 Type Safety & Routing
- **Type Generation:**
  - We will strictly define TypeScript interfaces that mirror our backend `API Resources` and `DTOs`.
  - *Strategy:* Use `spatie/laravel-typescript-transformer` to automatically generate `resources/js/types/generated.d.ts` from PHP classes.
- **Routing (Laravel Wayfinder):**
  - Use **Laravel Wayfinder** to generate fully-typed, importable TypeScript functions for Laravel controllers and routes.
  - **Benefit:** This eliminates hardcoded URLs and provides IDE autocompletion and type checking for all API calls and route parameters.
  - *Example:* Instead of `route('orders.store')`, we call generated functions like `orders.store()` which are automatically synced with the backend.

### 4.5 Component Abstractions
- **UI Primitives:**
  - Build atomic components (`Button`, `Card`, `Badge`) using `class-variance-authority` (CVA) to manage Tailwind variants.
- **Composed Components:**
  - **`ResourceTable`:** A higher-order component that accepts a Laravel `PaginatedResource` prop and renders a table with server-side sorting and pagination links automatically.
  - **`StatWidget`:** A standardized card for dashboard metrics that accepts `trend` (up/down), `value`, and `label`.

---

## 5. Execution Plan

### Step 1: Scaffolding & Configuration
- [x] Finalize Laravel and Inertia project scaffolding.
- [x] Configure core packages and environment settings.

### Step 2: Database Layer
- [x] Create Migrations for all core entities.
- [x] Create Eloquent Models & Relationships.
- [x] **Action:** Create `InventoryObserver` to watch for low stock.

### Step 3: Core Services & DI
- [ ] Define DTOs (`InventoryAdvisoryDTO`, `InventoryContextDTO`).
- [ ] Implement `InventoryMathService` (Port logic).
- [ ] Implement `InventoryManagementService` using **Strategy Pattern**.
- [ ] Implement `GeminiService` & `AiProviderInterface`.

### Step 4: Game Logic & Events
- [ ] Define Events: `OrderPlaced`, `TransferCompleted`, `SpikeOccurred`.
- [ ] Implement **State Machines** for `Order` and `Transfer` models.
- [ ] Implement **`SpikeEventFactory`** for simulation events.
- [ ] Create Listeners to handle `GameState` updates (XP gain, Cash deduction).
- [ ] Implement `SimulationService` (The clock).

### Step 5: Frontend Connection & Infrastructure
- [ ] **Infrastructure:** Configure `HandleInertiaRequests` middleware to share `auth` and `game` state.
- [ ] **Type Safety:** Configure `spatie/laravel-typescript-transformer` to generate `types/generated.d.ts`.
- [ ] **Routing:** Setup **Laravel Wayfinder** to generate typed route helpers.
- [ ] **Layouts:** Implement `GameLayout.tsx` with persistent navigation and Sidebar.
- [ ] **Components:** Refactor existing React components into "dumb" UI atoms and "smart" Inertia Pages.
- [ ] **Routing:** Update `routes/web.php` and bind Inertia Pages to Controllers.