# Changelog

All notable changes to the Moonshine Coffee Management Sim project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [January 24, 2026] - Documentation Synchronization

### Changed
- Updated CLAUDE.md with comprehensive service documentation
  - Added all backend services (DemandSimulationService, SpikeResolutionService, PricingService, etc.)
  - Added all frontend services with descriptions
  - Documented key models including Alert, DailyReport, and inventory_history
  - Added event system documentation with all listeners
  - Added notification system overview
  - Added analytics system overview
  - Added spike events system overview
- Updated README.md with new features
  - Enhanced Analytics & Reporting section with tabbed interface details
  - Added Notification & Alert System section
  - Updated Directory Structure with detailed file organization
  - Enhanced Event System documentation with listener details
- Updated docs/INDEX.md
  - Added references to notification-system.md and analytics-page-audit.md
  - Updated implemented features list with recent additions
  - Updated planned enhancements
  - Added "Recent Updates" section

### Added
- Created docs/CHANGELOG.md to track project changes
- Documented analytics refactor implementation
- Documented notification/alert system architecture

---

## [January 23, 2026] - Analytics Refactor Complete & Notification System Documentation

### Added
- **Analytics Page Refactor**:
  - Tabbed UI with Overview, Logistics, and Financials tabs
  - `OverviewTab.tsx` component for high-level KPIs
  - `LogisticsTab.tsx` component for transfer and fulfillment metrics
  - `FinancialsTab.tsx` component for spending analysis
  - `CollapsibleSection.tsx` reusable UI component
  - `tabs.tsx` Radix UI wrapper component
  - Historical inventory tracking via `inventory_history` table
  - Daily aggregated metrics via `daily_reports` table
  - `SnapshotInventoryLevels` listener for inventory snapshots
  - `CreateDailyReport` listener for daily aggregations

- **Notification System Documentation**:
  - Created comprehensive `docs/notification-system.md`
  - Documented custom Alert model architecture
  - Documented event-driven alert generation
  - Documented Comms Log UI implementation
  - Documented alert types and navigation patterns

### Changed
- Enhanced analytics data providers in `GameController.php`
- Added storage utilization metrics
- Added fulfillment rate tracking
- Added spike impact analysis
- Improved analytics page responsiveness

### Fixed
- Analytics data integrity issues
- Collapsible section UI behavior
- Tab navigation state management

---

## [January 22, 2026] - Spike War Room Complete

### Added
- **Spike War Room UI**:
  - Comprehensive spike history page at `/game/spike-history`
  - Active spike display with countdown timers
  - Historical spike logs with resolution status
  - Resolution strategy suggestions
  - Animated spike resolution feedback

- **Spike Resolution Service**:
  - `SpikeResolutionService.php` for handling spike resolution
  - Resolution strategies for different spike types
  - Integration with spike lifecycle management

- **Testing**:
  - `SpikeWarRoomTest.php` for UI integration testing
  - `SpikeSimulationTest.php` for spike lifecycle testing
  - `DemandSimulationTest.php` for demand simulation scoping

### Changed
- Enhanced spike event display with better visual hierarchy
- Improved spike constraint checking
- Scoped demand simulation to user context

### Fixed
- Spike resolve animation gating issue
- User scoping in DemandSimulationService

---

## [January 21-23, 2026] - Analytics Phase 3 & 4

### Added
- **Phase 3: Extended Analytics Logic**:
  - Storage utilization metrics calculation
  - Fulfillment rate tracking
  - Spike impact analysis (7-day window)
  - `StorageUtilizationTest.php` for storage metrics
  - `FulfillmentMetricsTest.php` for fulfillment tracking
  - `SpikeImpactTest.php` for spike impact analysis
  - Manual verification script: `verify_phase3_analytics.php`

- **Phase 4: Tabbed UI Implementation**:
  - Recharts dependency for data visualization
  - Tab-based analytics interface
  - Overview, Logistics, and Financials tabs
  - Collapsible sections for better UX
  - `AnalyticsPageTest.php` for UI testing
  - Manual verification script: `verify_analytics_data_integrity.php`

### Changed
- Refactored analytics data providers
- Enhanced data integrity checks
- Improved analytics page performance

---

## [January 21, 2026] - Analytics Phase 1 & 2

### Added
- **Phase 1: Database Migrations & Historical Tracking**:
  - `inventory_history` table for historical inventory snapshots
  - `unit_price` and `category` index on products table
  - `SnapshotInventoryLevels` listener for inventory tracking
  - Listener registration in `GameServiceProvider`
  - `InventoryHistoryMigrationTest.php`
  - `ProductMigrationTest.php`
  - `SnapshotInventoryLevelsTest.php`
  - Manual verification script: `verify_analytics_phase1.php`

- **Phase 2: Data Provider Refactoring**:
  - Enhanced analytics data providers in `GameController.php`
  - Real-time inventory calculations
  - `AnalyticsPageTest.php` for analytics page testing
  - Manual verification script: `verify_analytics_phase2.php`

### Changed
- Updated Product model with unit_price and category fields
- Enhanced analytics page data structure

---

## [January 19, 2026] - Spike Event System Enhancement

### Added
- **GuaranteedSpikeGenerator**: Ensures spike coverage after Day 1
- **SpikeConstraintChecker**: Enforces spike scheduling constraints
  - Max 2 concurrent spikes per day
  - 2-day cooldown between same spike types
- Enhanced spike event lifecycle management

### Changed
- Improved spike generation logic in `SimulationService`
- Enhanced spike event factory with better randomization
- Updated spike event UI with better status indicators

---

## [January 16-17, 2026] - Alert System & Daily Reports

### Added
- **Alert System**:
  - `Alert` model for custom notifications
  - `alerts` table migration with indexes
  - `GenerateAlert` listener for event-driven alerts
  - Alert types: order_placed, transfer_completed, spike_occurred, low_stock, isolation
  - Comms Log UI component in Layout.tsx
  - Smart navigation from alerts to relevant pages

- **Daily Reports**:
  - `DailyReport` model for daily snapshots
  - `daily_reports` table migration
  - `CreateDailyReport` listener
  - Daily metrics aggregation

### Changed
- Enhanced HandleInertiaRequests middleware to share alerts
- Updated GameContext to include alerts
- Improved event listener architecture

---

## [January 16, 2026] - Multi-Hop Logistics

### Added
- Multi-hop routing system via `LogisticsService`
- Route model for location-to-location connections
- Shipment tracking for multi-leg transfers
- Route vulnerability to weather events (blizzard spikes)

### Changed
- Enhanced transfer system with multi-hop support
- Updated ordering logic to support multi-hop routing
- Improved logistics calculations

---

## [January 15-16, 2026] - Core Game Systems

### Added
- Initial Laravel 12 + React 19 + Inertia.js setup
- Core models: User, GameState, Location, Product, Vendor, Inventory, Order, Transfer
- State machines for Order and Transfer lifecycle
- Event system with listeners
- Simulation service for time advancement
- Inventory management service
- Spike event system with 5 types (demand, delay, price, breakdown, blizzard)
- Dashboard with KPIs
- Ordering interface with vendor selection
- Transfer management UI
- Inventory tracking UI

### Technical Stack
- Laravel 12 with PHP 8.2+
- React 19 with TypeScript
- Inertia.js 2.0 for SSR
- TailwindCSS 4.0
- Radix UI components
- PostgreSQL database
- Vite 7 for asset compilation
- Pest for testing

---

## Format

Each entry should follow this structure:

```
## [YYYY-MM-DD] - Descriptive Title

### Added
- New features or capabilities

### Changed
- Modifications to existing functionality

### Deprecated
- Features marked for removal in future versions

### Removed
- Features removed in this version

### Fixed
- Bug fixes and corrections

### Security
- Security-related changes
```
