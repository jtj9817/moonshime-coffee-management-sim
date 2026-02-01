# Moonshine Coffee Management Sim

A Laravel 12 + React 19 simulation game for managing coffee shop inventory and supply chain operations across multiple locations. Built with Inertia.js for a seamless SPA-like experience with server-side rendering.

## Technology Stack

- **Backend**: Laravel 12 (PHP 8.2+) with PostgreSQL
  - Runtime: PHP 8.4.1 (backward compatible with PHP 8.2+)
- **Frontend**: React 19 + TypeScript via Inertia.js 2.0
  - Build Tool: Vite 7.0.4
  - TypeScript: 5.7.2
- **Styling**: TailwindCSS 4.0 with Radix UI components
- **Real-time**: Laravel Reverb for dashboard updates (planned)
- **State Management**: Laravel state machines via `spatie/laravel-model-states`
- **Routing**: Laravel Wayfinder for type-safe routing
- **AI Integration**: Echolabs Prism for AI-powered inventory advisories
- **Package Management**: pnpm for frontend, Composer for backend
- **Testing**: Pest 4.3 with Laravel plugin

## Project Overview

Moonshine Coffee Management Sim is a supply chain management simulation game. Players manage three coffee shop locations:

- **Moonshine HQ (Roastery)** - Central roastery and warehouse (5,000 unit capacity)
- **Uptown Kiosk** - Small downtown kiosk (500 unit capacity)
- **Lakeside Cafe** - Larger cafe location (1,200 unit capacity)

Players must balance inventory across locations, place orders from multiple suppliers, manage transfers between locations, and respond to random demand spikes while optimizing costs and avoiding stockouts and waste.

## Core Features

### Inventory Management
- Track inventory levels across all three locations
- Monitor perishable items with expiration dates (Oat Milk, Pumpkin Spice, Bacon Gouda sandwiches)
- Calculate reorder points and safety stock levels
- Handle waste from expired or spoiled inventory

### Supplier Management
- Multiple suppliers with different trade-offs (reliability vs price vs speed)
  - BeanCo Global - High reliability, standard delivery, premium pricing
  - RapidSupplies - Fast delivery, moderate pricing, lower reliability
  - Dairy Direct - Excellent reliability for dairy products
  - ValueBulk - Best prices, slow delivery, lowest reliability
- Tiered pricing with volume discounts
- Supplier performance metrics tracking (late rate, fill rate, complaint rate)

### Demand Spikes & Events
- Random demand spikes (Chaos Monkey events)
- Heatwaves, local festivals, viral social media trends
- Supplier strikes and delivery delays
- Spike resolution and recovery mechanics

### Transfer Logistics
- Transfer inventory between locations
- Handle in-transit transfers with state management
- Balance stock across locations based on demand patterns

### AI-Powered Advisories
- AI-generated inventory recommendations
- Contextual advice based on current stock levels
- Supplier selection assistance

### Analytics & Reporting
- **Analytics Dashboard** with tabbed interface (Overview, Logistics, Financials)
- Real-time inventory position reports with historical trends
- Vendor performance analytics and supplier comparisons
- Waste tracking and cost analysis
- Storage utilization metrics across locations
- Fulfillment rate tracking and spike impact analysis
- Historical inventory snapshots via `inventory_history` table
- Daily aggregated metrics via `daily_reports` table
- Collapsible sections for detailed drill-down

### Notification & Alert System
- **Custom Alert System** (non-standard Laravel notifications)
- Event-driven alert generation for orders, transfers, spikes, and stock issues
- "Comms Log" HUD panel with military/tactical aesthetic
- Severity indicators (critical, warning, info)
- Smart navigation - click alerts to jump to relevant pages
- Alert types: order placed, transfer completed, spike occurred, low stock, location isolation
- Alerts shared globally via Inertia middleware
- Unread badge with animation

## Architecture

### Backend Design Patterns

The application follows strict architectural patterns for maintainability and scalability:

- **Dependency Injection**: Services injected via constructor, no direct instantiation in controllers
- **Service Providers**: `GameServiceProvider` binds interfaces to implementations
- **Observer Pattern**: Events decouple actions from side effects (e.g., `OrderPlaced` event triggers cash deduction, notifications, metrics updates)
- **State Pattern**: Finite state machines for Order and Transfer lifecycles (`Draft` -> `Pending` -> `Shipped` -> `Delivered`)
- **Strategy Pattern**: Restock strategies (`JustInTimeStrategy`, `SafetyStockStrategy`) based on user policy settings
- **Factory Pattern**: `SpikeEventFactory` generates random simulation events
- **DTOs**: Strictly typed data transfer objects (`InventoryAdvisoryData`, `InventoryContextDTO`)

### Frontend Architecture

- **Inertia.js Pages**: React pages at `resources/js/Pages/game/` corresponding to Laravel routes
- **Persistent Layouts**: `GameLayout.tsx` preserves navigation and HUD during navigation
- **Global State**: Shared via Inertia middleware (`HandleInertiaRequests`) instead of client-side store
- **Partial Reloads**: Optimized updates for polling components (`router.reload({ only: ['spikes'] })`)
- **Form Handling**: Inertia's `useForm` hook with automatic validation error mapping
- **Type Safety**: TypeScript interfaces mirror backend DTOs and API Resources

## Directory Structure

```
moonshine-coffee-management-sim/
├── app/
│   ├── Actions/          # Fortify actions for authentication
│   ├── Concerns/         # Reusable traits and concerns
│   ├── Console/          # Artisan commands
│   ├── DTOs/             # Data Transfer Objects for type-safe data passing
│   ├── Events/           # Laravel events (TimeAdvanced, OrderPlaced, SpikeOccurred, etc.)
│   ├── Http/
│   │   ├── Controllers/  # Request handlers with Inertia responses
│   │   ├── Middleware/   # HTTP middleware
│   │   └── Requests/     # Form request validation classes
│   ├── Interfaces/       # Service interfaces for dependency injection
│   ├── Listeners/        # Event listeners (ProcessDeliveries, SnapshotInventory, GenerateAlert, etc.)
│   ├── Models/           # Eloquent models (GameState, Location, Product, Inventory, Order, Alert, etc.)
│   ├── Observers/        # Model observers
│   ├── Providers/        # Service providers (AppServiceProvider, GameServiceProvider, etc.)
│   ├── Services/         # Business logic services
│   │   ├── Spikes/       # Spike-related services (SpikeEventFactory, GuaranteedSpikeGenerator, etc.)
│   │   ├── Strategies/   # Strategy pattern implementations (restock strategies)
│   │   └── *Service.php  # Core services (Simulation, Order, Logistics, Pricing, etc.)
│   └── States/           # State machine definitions
│       ├── Order/        # Order state transitions
│       └── Transfer/     # Transfer state transitions
├── resources/js/
│   ├── actions/          # Server actions and mutations
│   ├── assets/           # Static assets (images, icons)
│   ├── components/       # Reusable React UI components
│   │   ├── analytics/    # Analytics tab components (OverviewTab, LogisticsTab, FinancialsTab)
│   │   ├── game/         # Game-specific components
│   │   └── ui/           # Radix UI components (tabs, collapsible, dialogs, etc.)
│   ├── contexts/         # React contexts (game-context for state and alerts)
│   ├── hooks/            # Custom React hooks
│   ├── layouts/          # Persistent layouts
│   │   ├── app/          # App layout components
│   │   ├── auth/         # Auth layout components
│   │   ├── settings/     # Settings layout components
│   │   └── game-layout.tsx  # Main game layout with HUD and Comms Log
│   ├── lib/              # Utility libraries and helpers
│   ├── pages/            # Inertia.js page components
│   │   ├── auth/         # Authentication pages
│   │   ├── game/         # Game pages
│   │   │   ├── dashboard.tsx      # Main HUD with KPIs and alerts
│   │   │   ├── inventory.tsx      # Inventory levels across locations
│   │   │   ├── ordering.tsx       # Order placement with vendor selection
│   │   │   ├── transfers.tsx      # Inter-location transfer management
│   │   │   ├── vendors.tsx        # Vendor performance and negotiation
│   │   │   ├── analytics.tsx      # Analytics dashboard with tabs
│   │   │   ├── reports.tsx        # Reporting and historical data
│   │   │   ├── spike-history.tsx  # Spike War Room with active/historical spikes
│   │   │   ├── sku-detail.tsx     # SKU-level detail view
│   │   │   ├── strategy.tsx       # Strategy and policy management
│   │   │   └── welcome.tsx        # Welcome/onboarding page
│   │   └── settings/     # Settings pages
│   ├── routes/           # Frontend routing definitions
│   ├── services/         # Frontend services
│   │   ├── cockpitService.ts      # Dashboard alerts and KPIs
│   │   ├── inventoryService.ts    # Inventory calculations
│   │   ├── transferService.ts     # Transfer logistics
│   │   ├── vendorService.ts       # Supplier scoring
│   │   ├── wasteService.ts        # Waste tracking
│   │   ├── policyService.ts       # Inventory policy management
│   │   ├── orderCalculations.ts   # Order quantity calculations
│   │   ├── skuMath.ts             # SKU-level math (reorder points, safety stock)
│   │   └── geminiService.ts       # AI integration helpers
│   ├── types/            # TypeScript type definitions
│   │   ├── game.ts       # Game-specific types
│   │   └── index.d.ts    # Global type declarations
│   ├── wayfinder/        # Laravel Wayfinder routing integration
│   ├── constants.ts      # Game constants (LOCATIONS, ITEMS, SUPPLIERS)
│   ├── app.tsx           # Main application entry point
│   └── ssr.tsx           # SSR entry point
├── database/
│   ├── migrations/       # Schema migrations (including inventory_history, daily_reports, alerts)
│   ├── factories/        # Model factories for testing
│   └── seeders/          # Database seeders
├── tests/
│   ├── Feature/          # Feature tests
│   │   ├── Analytics/    # Analytics feature tests
│   │   ├── Auth/         # Authentication tests
│   │   ├── Seeder/       # Seeder tests
│   │   ├── Settings/     # Settings tests
│   │   └── *.php         # Other feature tests (Order, MultiHopOrderTest, etc.)
│   ├── Unit/             # Unit tests
│   │   ├── Actions/      # Action tests
│   │   ├── DTOs/         # DTO tests
│   │   ├── Events/       # Event tests
│   │   ├── Interfaces/   # Interface tests
│   │   ├── Listeners/    # Listener tests
│   │   ├── Models/       # Model tests
│   │   ├── Seeders/      # Seeder unit tests
│   │   └── Services/     # Service tests
│   ├── Traits/           # Reusable test traits (MultiHopScenarioBuilder)
│   └── manual/           # Manual verification scripts for phased development
├── docs/                 # Comprehensive documentation
│   ├── architecture/     # Architecture documentation
│   ├── archived-plans/   # Archived planning documents
│   ├── backend/          # Backend architecture docs
│   ├── domain/           # Game mechanics and business logic
│   ├── frontend/         # Frontend architecture
│   ├── tickets/          # Test failure tickets and resolutions
│   ├── notification-system.md         # Alert system documentation
│   ├── analytics-page-audit.md        # Analytics feature mapping
│   ├── multi-hop-order-test-scenarios.md  # Multi-hop test scenarios
│   ├── CHANGELOG.md      # Project changelog
│   ├── CRITICAL-BUGS.md  # Critical bug tracking (all resolved)
│   ├── INDEX.md          # Central documentation hub
│   └── *.md              # Additional documentation files
├── conductor/            # Development workflow tracking
│   ├── archive/          # Archived completed tracks
│   ├── code_styleguides/ # Code style guides
│   └── tracks/           # Active feature/refactor implementation tracks
│       └── multi_hop_regression_20260128/  # Multi-hop test infrastructure
├── routes/               # Laravel routes (web.php with game routes)
├── config/               # Laravel configuration files
├── public/               # Public assets and entry points
└── bootstrap/            # Laravel bootstrap files
```

## Game Entities

### Locations
- Moonshine HQ (Roastery) - 5,000 capacity
- Uptown Kiosk - 500 capacity
- Lakeside Cafe - 1,200 capacity

### Product Categories
- Beans (Espresso Blend)
- Milk (Oat Milk, Almond Milk)
- Cups (12oz Paper Cups)
- Syrups (Vanilla, Dark Mocha)
- Sauces (Pumpkin Spice)
- Tea (Earl Grey)
- Sugar (Raw Sugar)
- Food (Bacon Gouda Sandwiches)
- Cleaning (Sanitizer Spray)

### Suppliers
- BeanCo Global - 95% reliability, 3-day delivery, premium pricing
- RapidSupplies - 85% reliability, 1-day delivery, moderate pricing
- Dairy Direct - 98% reliability, 1-day delivery for dairy
- ValueBulk - 70% reliability, 7-day delivery, discount pricing

## Getting Started

### Prerequisites
- PHP 8.2+
- Composer
- Node.js 18+
- pnpm
- PostgreSQL (or use Sail for Docker)

### Quick Start with Docker (Recommended)

```bash
# Clone the repository
git clone <repository-url>
cd moonshine-coffee-management-sim

# Copy environment file
cp .env.example .env

# Start containers and install dependencies
./vendor/bin/sail up -d
./vendor/bin/sail composer install
./vendor/bin/sail npm install

# Generate application key and run migrations
./vendor/bin/sail php artisan key:generate
./vendor/bin/sail php artisan migrate

# Build assets
./vendor/bin/sail npm run dev

# Access the application at http://localhost:8082
```

### Local Development without Docker

```bash
# Install PHP dependencies
composer install

# Install JavaScript dependencies
pnpm install

# Copy environment and configure database
cp .env.example .env
# Edit .env to set your database credentials

# Generate key
php artisan key:generate

# Run migrations
php artisan migrate

# Start development server
composer dev

# Or start services separately:
php artisan serve
php artisan queue:listen
php artisan pail
pnpm dev
```

### Database Setup

The project uses PostgreSQL by default. Configure your database in `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=moonshine_coffee
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Or use SQLite for local development:

```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

## Development Commands

### Backend

```bash
# Run full development stack (Laravel + queue + logs + Vite)
composer dev

# Run with SSR support
composer dev:ssr

# PHP code formatting (Pint)
composer lint

# Run tests with lint check
composer test

# Laravel Artisan commands
php artisan migrate
php artisan migrate:fresh --seed
php artisan tinker
```

### Frontend

```bash
# Vite dev server
pnpm dev

# Production builds
pnpm build
pnpm build:ssr

# ESLint with auto-fix
pnpm lint

# Prettier formatting
pnpm format
pnpm format:check

# TypeScript type checking
pnpm types
```

### Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=InventoryTest

# Run Pest tests
vendor/bin/pest
```

## Game Mechanics

### Inventory Calculations

The simulation uses sophisticated inventory math including:

- **Reorder Point**: `Average Daily Demand × Lead Time + Safety Stock`
- **Economic Order Quantity (EOQ)**: Optimizes ordering costs vs holding costs
- **Safety Stock**: Buffer stock to prevent stockouts during demand spikes
- **Service Levels**: Target fill rates (95%, 98%, 99%)

### State Machine Transitions

Orders and transfers follow strict state transitions:

```
Order: Draft -> Pending -> Shipped -> Delivered -> Cancelled
Transfer: Requested -> Approved -> InTransit -> Completed -> Cancelled
```

### Event System

The simulation uses Laravel events for decoupled game logic:

- `TimeAdvanced`: Triggers delivery processing, perishable decay, spike generation, inventory snapshots, daily reports, storage costs
- `OrderPlaced`: Deducts cash, updates metrics, generates alerts
- `TransferCompleted`: Updates inventory, generates alerts
- `SpikeOccurred`: Applies spike effects, generates alerts
- `SpikeEnded`: Rolls back spike effects, updates state

Event listeners handle side effects:
- `ProcessDeliveries` - Processes order arrivals
- `DecayPerishables` - Handles item expiration
- `GenerateAlert` - Creates notifications for game events
- `SnapshotInventoryLevels` - Records inventory history for analytics
- `CreateDailyReport` - Generates daily aggregated metrics
- `ApplySpikeEffect` - Applies spike effects (demand, delay, breakdown, etc.)
- `RollbackSpikeEffect` - Removes spike effects when expired
- `ApplyStorageCosts` - Deducts storage costs

## Code Quality

### PHP Standards
- PSR-12 coding standard
- Laravel Pint for automatic code formatting
- Type hints on all methods
- PHPDoc for complex logic

### TypeScript Standards
- Strict mode enabled
- ESLint with React plugin
- Prettier for formatting
- Proper interface definitions

### Testing
- Feature tests for controller actions
- Unit tests for services and models
- Factory classes for test data generation
- RefreshDatabase trait for test isolation

## Configuration

### Environment Variables

Key environment variables in `.env`:

```env
APP_NAME=MoonshineCoffeeSim
APP_URL=http://localhost:8082
APP_DEBUG=true

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=moonshine_coffee

# Vite ports
VITE_PORT=5174
```

### AI Configuration

The application uses Echolabs Prism for AI integrations. Configure API keys in your environment or service provider.

## License

This project is open-source and available under the MIT License.
