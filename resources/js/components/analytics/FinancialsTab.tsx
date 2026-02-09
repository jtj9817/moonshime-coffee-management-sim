import { CheckCircle, Clock, Package, TrendingUp } from 'lucide-react';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatCurrency } from '@/lib/formatCurrency';

interface SpendingByCategoryItem {
    category: string;
    amount: number;
}

interface FulfillmentMetrics {
    totalOrders: number;
    deliveredOrders: number;
    fulfillmentRate: number;
    averageDeliveryTime: number;
}

interface FinancialsTabProps {
    spendingByCategory: SpendingByCategoryItem[];
    fulfillmentMetrics: FulfillmentMetrics;
}

const categoryColors: Record<string, string> = {
    'Coffee Beans': 'bg-amber-500',
    Dairy: 'bg-blue-500',
    Syrups: 'bg-purple-500',
    'Cups & Lids': 'bg-emerald-500',
    Pastries: 'bg-rose-500',
    Tea: 'bg-green-500',
    Equipment: 'bg-gray-500',
};

function getCategoryColor(category: string): string {
    return categoryColors[category] || 'bg-stone-500';
}

export function FinancialsTab({
    spendingByCategory,
    fulfillmentMetrics,
}: FinancialsTabProps) {
    const totalSpending = spendingByCategory.reduce(
        (sum, cat) => sum + cat.amount,
        0,
    );

    return (
        <div className="flex flex-col gap-6">
            {/* Order Fulfillment Metrics */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Package className="h-5 w-5" />
                        Order Fulfillment Metrics
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div className="rounded-lg border border-stone-200 p-4 dark:border-stone-700">
                            <div className="flex items-center gap-2 text-stone-500">
                                <Package className="h-4 w-4" />
                                <span className="text-sm">Total Orders</span>
                            </div>
                            <div className="mt-2 text-2xl font-bold text-stone-900 dark:text-white">
                                {fulfillmentMetrics.totalOrders}
                            </div>
                        </div>
                        <div className="rounded-lg border border-stone-200 p-4 dark:border-stone-700">
                            <div className="flex items-center gap-2 text-stone-500">
                                <CheckCircle className="h-4 w-4" />
                                <span className="text-sm">Delivered</span>
                            </div>
                            <div className="mt-2 text-2xl font-bold text-emerald-600">
                                {fulfillmentMetrics.deliveredOrders}
                            </div>
                        </div>
                        <div className="rounded-lg border border-stone-200 p-4 dark:border-stone-700">
                            <div className="flex items-center gap-2 text-stone-500">
                                <TrendingUp className="h-4 w-4" />
                                <span className="text-sm">
                                    Fulfillment Rate
                                </span>
                            </div>
                            <div className="mt-2 text-2xl font-bold text-blue-600">
                                {fulfillmentMetrics.fulfillmentRate}%
                            </div>
                        </div>
                        <div className="rounded-lg border border-stone-200 p-4 dark:border-stone-700">
                            <div className="flex items-center gap-2 text-stone-500">
                                <Clock className="h-4 w-4" />
                                <span className="text-sm">
                                    Avg Delivery Time
                                </span>
                            </div>
                            <div className="mt-2 text-2xl font-bold text-amber-600">
                                {fulfillmentMetrics.averageDeliveryTime} days
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Spending by Category */}
            <Card>
                <CardHeader>
                    <CardTitle>Spending by Category</CardTitle>
                </CardHeader>
                <CardContent>
                    {spendingByCategory.length > 0 ? (
                        <div className="space-y-4">
                            {spendingByCategory.map((cat, i) => {
                                const percentage =
                                    totalSpending > 0
                                        ? (cat.amount / totalSpending) * 100
                                        : 0;
                                return (
                                    <div key={i}>
                                        <div className="mb-1 flex items-center justify-between text-sm">
                                            <div className="flex items-center gap-2">
                                                <div
                                                    className={`h-3 w-3 rounded-full ${getCategoryColor(cat.category)}`}
                                                />
                                                <span className="font-medium text-stone-900 dark:text-white">
                                                    {cat.category}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <span className="text-stone-500">
                                                    {percentage.toFixed(1)}%
                                                </span>
                                                <span className="font-medium text-stone-900 dark:text-white">
                                                    $
                                                    {formatCurrency(cat.amount)}
                                                </span>
                                            </div>
                                        </div>
                                        <div className="h-2 w-full overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                                            <div
                                                className={`h-full transition-all ${getCategoryColor(cat.category)}`}
                                                style={{
                                                    width: `${percentage}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                            <div className="mt-4 border-t border-stone-200 pt-4 dark:border-stone-700">
                                <div className="flex items-center justify-between">
                                    <span className="font-semibold text-stone-900 dark:text-white">
                                        Total Spending
                                    </span>
                                    <span className="text-xl font-bold text-amber-600">
                                        ${formatCurrency(totalSpending)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div className="py-8 text-center text-stone-500">
                            No spending data yet. Place orders to see spending
                            breakdown.
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
