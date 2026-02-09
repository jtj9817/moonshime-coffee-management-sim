import { BarChart3, DollarSign, TrendingUp } from 'lucide-react';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CollapsibleSection } from '@/components/ui/collapsible-section';
import { formatCurrency } from '@/lib/formatCurrency';

interface InventoryTrendPoint {
    day: number;
    value: number;
}

interface LocationComparisonItem {
    name: string;
    inventoryValue: number;
    utilization: number;
    itemCount: number;
}

interface OverviewTabProps {
    overviewMetrics: {
        cash: number;
        netWorth: number;
        revenue7Day: number;
    };
    inventoryTrends: InventoryTrendPoint[];
    locationComparison: LocationComparisonItem[];
}

export function OverviewTab({
    overviewMetrics,
    inventoryTrends,
    locationComparison,
}: OverviewTabProps) {
    const maxTrendValue = Math.max(...inventoryTrends.map((p) => p.value), 1);

    return (
        <div className="flex flex-col gap-6">
            {/* Summary Cards */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium text-stone-500">
                            Cash on Hand
                        </CardTitle>
                        <DollarSign className="h-4 w-4 text-emerald-500" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">
                            ${formatCurrency(overviewMetrics.cash)}
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium text-stone-500">
                            Net Worth
                        </CardTitle>
                        <TrendingUp className="h-4 w-4 text-amber-500" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">
                            ${formatCurrency(overviewMetrics.netWorth)}
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium text-stone-500">
                            7-Day Revenue
                        </CardTitle>
                        <BarChart3 className="h-4 w-4 text-blue-500" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">
                            ${formatCurrency(overviewMetrics.revenue7Day)}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Charts Grid */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {/* Inventory Trends */}
                <CollapsibleSection title="Inventory Trends">
                    <Card>
                        <CardContent className="pt-6">
                            {inventoryTrends.length > 0 ? (
                                <>
                                    <div className="flex h-64 items-end gap-2">
                                        {inventoryTrends.map((point, i) => (
                                            <div
                                                key={i}
                                                className="flex-1 rounded-t bg-amber-500 transition-all hover:bg-amber-600"
                                                style={{
                                                    height: `${(point.value / maxTrendValue) * 100}%`,
                                                    minHeight: '20px',
                                                }}
                                                title={`Day ${point.day}: ${point.value} units`}
                                            />
                                        ))}
                                    </div>
                                    <div className="mt-2 flex justify-between text-xs text-stone-500">
                                        {inventoryTrends.map((point, i) => (
                                            <span key={i}>Day {point.day}</span>
                                        ))}
                                    </div>
                                </>
                            ) : (
                                <div className="flex h-64 items-center justify-center text-stone-500">
                                    No inventory history data yet. Advance a few
                                    days to see trends.
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </CollapsibleSection>

                {/* Location Comparison */}
                <CollapsibleSection title="Location Comparison">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="space-y-4">
                                {locationComparison.map((loc, i) => (
                                    <div
                                        key={i}
                                        className="rounded-xl border border-stone-200 p-4 dark:border-stone-700"
                                    >
                                        <div className="flex items-center justify-between">
                                            <h4 className="font-semibold text-stone-900 dark:text-white">
                                                {loc.name}
                                            </h4>
                                            <span className="text-sm text-stone-500">
                                                {loc.itemCount} items
                                            </span>
                                        </div>
                                        <p className="mt-1 text-2xl font-bold text-amber-600">
                                            $
                                            {formatCurrency(loc.inventoryValue)}
                                        </p>
                                        <div className="mt-2 flex items-center gap-2">
                                            <div className="h-2 flex-1 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                                                <div
                                                    className="h-full bg-emerald-500"
                                                    style={{
                                                        width: `${Math.min(loc.utilization, 100)}%`,
                                                    }}
                                                />
                                            </div>
                                            <span className="text-xs text-stone-500">
                                                {loc.utilization}% utilized
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </CollapsibleSection>
            </div>
        </div>
    );
}
