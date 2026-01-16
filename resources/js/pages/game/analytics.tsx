import { Head } from '@inertiajs/react';
import { BarChart3, PieChart, TrendingUp } from 'lucide-react';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import GameLayout from '@/layouts/game-layout';
import { type BreadcrumbItem } from '@/types';

interface AnalyticsProps {
    inventoryTrends: Array<{ day: number; value: number }>;
    spendingByCategory: Array<{ category: string; amount: number }>;
    locationComparison: Array<{ name: string; inventoryValue: number }>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mission Control', href: '/game/dashboard' },
    { title: 'Analytics', href: '/game/analytics' },
];

export default function Analytics({
    inventoryTrends,
    spendingByCategory,
    locationComparison,
}: AnalyticsProps) {
    const totalInventoryValue = locationComparison.reduce(
        (sum, loc) => sum + loc.inventoryValue,
        0,
    );
    const totalSpending = spendingByCategory.reduce((sum, cat) => sum + cat.amount, 0);

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
                        Insights and performance metrics
                    </p>
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-stone-500">
                                Total Inventory Value
                            </CardTitle>
                            <TrendingUp className="h-4 w-4 text-emerald-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                ${totalInventoryValue.toLocaleString()}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-stone-500">
                                Total Spending
                            </CardTitle>
                            <BarChart3 className="h-4 w-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                ${totalSpending.toLocaleString()}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-stone-500">
                                Categories Tracked
                            </CardTitle>
                            <PieChart className="h-4 w-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{spendingByCategory.length}</div>
                        </CardContent>
                    </Card>
                </div>

                {/* Charts Grid */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Inventory Trends */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Inventory Trends</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex h-64 items-end gap-2">
                                {inventoryTrends.map((point, i) => (
                                    <div
                                        key={i}
                                        className="flex-1 rounded-t bg-amber-500"
                                        style={{
                                            height: `${(point.value / Math.max(...inventoryTrends.map((p) => p.value))) * 100}%`,
                                            minHeight: '20px',
                                        }}
                                    />
                                ))}
                            </div>
                            <div className="mt-2 flex justify-between text-xs text-stone-500">
                                {inventoryTrends.map((point, i) => (
                                    <span key={i}>Day {point.day}</span>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Spending by Category */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Spending by Category</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {spendingByCategory.map((cat, i) => (
                                    <div key={i}>
                                        <div className="mb-1 flex items-center justify-between text-sm">
                                            <span className="font-medium">{cat.category}</span>
                                            <span className="text-stone-500">
                                                ${cat.amount.toLocaleString()}
                                            </span>
                                        </div>
                                        <div className="h-2 w-full overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                                            <div
                                                className="h-full bg-amber-500"
                                                style={{
                                                    width: `${(cat.amount / totalSpending) * 100}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Location Comparison */}
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Location Comparison</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                {locationComparison.map((loc, i) => (
                                    <div
                                        key={i}
                                        className="rounded-xl border border-stone-200 p-4 dark:border-stone-700"
                                    >
                                        <h4 className="font-semibold text-stone-900 dark:text-white">
                                            {loc.name}
                                        </h4>
                                        <p className="mt-1 text-2xl font-bold text-amber-600">
                                            ${loc.inventoryValue.toLocaleString()}
                                        </p>
                                        <p className="text-xs text-stone-500">Inventory Value</p>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </GameLayout>
    );
}
