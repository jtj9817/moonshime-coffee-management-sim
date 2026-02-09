import { Head } from '@inertiajs/react';
import { BarChart3, Boxes, DollarSign } from 'lucide-react';

import { FinancialsTab } from '@/components/analytics/FinancialsTab';
import { LogisticsTab } from '@/components/analytics/LogisticsTab';
import { OverviewTab } from '@/components/analytics/OverviewTab';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import GameLayout from '@/layouts/game-layout';
import { type BreadcrumbItem } from '@/types';

// ==================== Type Definitions ====================

interface InventoryTrendPoint {
    day: number;
    value: number;
}

interface SpendingByCategoryItem {
    category: string;
    amount: number;
}

interface LocationComparisonItem {
    name: string;
    inventoryValue: number;
    utilization: number;
    itemCount: number;
}

interface StorageUtilizationItem {
    location_id: string;
    name: string;
    capacity: number;
    used: number;
    percentage: number;
}

interface FulfillmentMetrics {
    totalOrders: number;
    deliveredOrders: number;
    fulfillmentRate: number;
    averageDeliveryTime: number;
}

interface SpikeImpact {
    min_inventory: number;
    avg_inventory: number;
}

interface SpikeImpactItem {
    id: string;
    type: string;
    name: string;
    start_day: number;
    end_day: number;
    product_name: string;
    location_name: string;
    impact: SpikeImpact | null;
}

/**
 * Enhanced Analytics Props - includes all data from Phase 2 & 3 backend methods
 */
interface EnhancedAnalyticsProps {
    // Phase 2: Existing metrics (refactored)
    overviewMetrics: {
        cash: number;
        netWorth: number;
        revenue7Day: number;
    };
    inventoryTrends: InventoryTrendPoint[];
    spendingByCategory: SpendingByCategoryItem[];
    locationComparison: LocationComparisonItem[];
    // Phase 3: Extended analytics
    storageUtilization: StorageUtilizationItem[];
    fulfillmentMetrics: FulfillmentMetrics;
    spikeImpactAnalysis: SpikeImpactItem[];
}

// ==================== Constants ====================

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mission Control', href: '/game/dashboard' },
    { title: 'Analytics', href: '/game/analytics' },
];

// ==================== Main Component ====================

export default function Analytics({
    overviewMetrics,
    inventoryTrends,
    spendingByCategory,
    locationComparison,
    storageUtilization,
    fulfillmentMetrics,
    spikeImpactAnalysis,
}: EnhancedAnalyticsProps) {
    // Computed values for Overview tab
    const totalInventoryValue = locationComparison.reduce(
        (sum, loc) => sum + loc.inventoryValue,
        0,
    );
    const totalSpending = spendingByCategory.reduce(
        (sum, cat) => sum + cat.amount,
        0,
    );

    return (
        <GameLayout breadcrumbs={breadcrumbs}>
            <Head title="Analytics" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-stone-900 dark:text-white">
                        Analytics Dashboard
                    </h1>
                    <p className="text-stone-500 dark:text-stone-400">
                        Insights and performance metrics across your supply
                        chain
                    </p>
                </div>

                {/* Tabbed Interface */}
                <Tabs defaultValue="overview" className="w-full">
                    <TabsList className="grid w-full grid-cols-3 lg:inline-flex lg:w-auto lg:grid-cols-none">
                        <TabsTrigger
                            value="overview"
                            className="flex items-center gap-2"
                        >
                            <BarChart3 className="h-4 w-4" />
                            <span>Overview</span>
                        </TabsTrigger>
                        <TabsTrigger
                            value="logistics"
                            className="flex items-center gap-2"
                        >
                            <Boxes className="h-4 w-4" />
                            <span>Logistics</span>
                        </TabsTrigger>
                        <TabsTrigger
                            value="financials"
                            className="flex items-center gap-2"
                        >
                            <DollarSign className="h-4 w-4" />
                            <span>Financials</span>
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="overview">
                        <OverviewTab
                            overviewMetrics={overviewMetrics}
                            inventoryTrends={inventoryTrends}
                            locationComparison={locationComparison}
                            totalInventoryValue={totalInventoryValue}
                            totalSpending={totalSpending}
                            categoriesCount={spendingByCategory.length}
                        />
                    </TabsContent>

                    <TabsContent value="logistics">
                        <LogisticsTab
                            storageUtilization={storageUtilization}
                            spikeImpactAnalysis={spikeImpactAnalysis}
                        />
                    </TabsContent>

                    <TabsContent value="financials">
                        <FinancialsTab
                            spendingByCategory={spendingByCategory}
                            fulfillmentMetrics={fulfillmentMetrics}
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </GameLayout>
    );
}
