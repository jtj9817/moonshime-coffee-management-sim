import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    MapPin,
    Package,
    ShoppingCart,
    TrendingUp,
} from 'lucide-react';

import DemandForecastChart from '@/components/DemandForecastChart';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import GameLayout from '@/layouts/game-layout';
import {
    ForecastRow,
    InventoryModel,
    LocationModel,
    ProductModel,
    type BreadcrumbItem,
} from '@/types';

interface SkuDetailProps {
    location: LocationModel;
    product: ProductModel;
    inventory: InventoryModel | null;
    forecast: ForecastRow[];
    currentDay: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mission Control', href: '/game/dashboard' },
    { title: 'Pantry', href: '/game/inventory' },
    { title: 'SKU Details', href: '#' },
];

function getStockStatus(quantity: number) {
    if (quantity === 0)
        return { label: 'Out of Stock', color: 'destructive' as const };
    if (quantity < 50)
        return { label: 'Low Stock', color: 'secondary' as const };
    if (quantity < 100)
        return { label: 'Medium Stock', color: 'default' as const };
    return { label: 'Well Stocked', color: 'default' as const };
}

export default function SkuDetail({
    location,
    product,
    inventory,
    forecast,
    currentDay,
}: SkuDetailProps) {
    const quantity = inventory?.quantity ?? 0;
    const status = getStockStatus(quantity);
    const maxStock = 500; // Placeholder for reorder point calculations
    const stockPercent = Math.min(100, (quantity / maxStock) * 100);

    return (
        <GameLayout breadcrumbs={breadcrumbs}>
            <Head title={`${product.name} at ${location.name}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-4">
                        <Link href="/game/inventory">
                            <Button variant="ghost" size="icon">
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <div className="mb-1 flex items-center gap-2">
                                <h1 className="text-2xl font-bold text-stone-900 dark:text-white">
                                    {product.name}
                                </h1>
                                <Badge variant={status.color}>
                                    {status.label}
                                </Badge>
                            </div>
                            <div className="flex items-center gap-2 text-stone-500">
                                <MapPin className="h-4 w-4" />
                                <span>{location.name}</span>
                                <span className="text-stone-300">|</span>
                                <Badge variant="outline">
                                    {product.category}
                                </Badge>
                            </div>
                        </div>
                    </div>
                    <Button className="gap-2 bg-amber-600 hover:bg-amber-700">
                        <ShoppingCart className="h-4 w-4" />
                        Quick Order
                    </Button>
                </div>

                {/* Stock Level Card */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Package className="h-5 w-5 text-amber-500" />
                            Current Stock Level
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center gap-6">
                            <div className="text-5xl font-bold text-stone-900 dark:text-white">
                                {quantity}
                            </div>
                            <div className="flex-1">
                                <div className="mb-2 flex items-center justify-between text-sm">
                                    <span className="text-stone-500">
                                        Stock Level
                                    </span>
                                    <span className="font-medium">
                                        {stockPercent.toFixed(0)}%
                                    </span>
                                </div>
                                <Progress
                                    value={stockPercent}
                                    className="h-3"
                                />
                                <p className="mt-2 text-sm text-stone-500">
                                    {quantity === 0
                                        ? 'Stock depleted - immediate reorder recommended'
                                        : quantity < 50
                                          ? 'Running low - consider reordering soon'
                                          : 'Stock levels are healthy'}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Demand Forecast Chart */}
                {forecast && forecast.length > 0 && (
                    <DemandForecastChart
                        forecast={forecast}
                        currentDay={currentDay}
                    />
                )}

                {/* Details Grid */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Product Info */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Product Information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between border-b border-stone-100 pb-3 dark:border-stone-800">
                                <span className="text-stone-500">Category</span>
                                <Badge variant="outline">
                                    {product.category}
                                </Badge>
                            </div>
                            <div className="flex items-center justify-between border-b border-stone-100 pb-3 dark:border-stone-800">
                                <span className="text-stone-500">
                                    Perishable
                                </span>
                                <span className="font-medium">
                                    {product.is_perishable ? 'Yes' : 'No'}
                                </span>
                            </div>
                            <div className="flex items-center justify-between border-b border-stone-100 pb-3 dark:border-stone-800">
                                <span className="text-stone-500">
                                    Storage Cost
                                </span>
                                <span className="font-medium">
                                    ${product.storage_cost.toFixed(2)}/unit/day
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-stone-500">
                                    Last Restocked
                                </span>
                                <span className="font-medium">
                                    {inventory?.last_restocked_at
                                        ? new Date(
                                              inventory.last_restocked_at,
                                          ).toLocaleDateString()
                                        : 'Never'}
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Location Info */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Location Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between border-b border-stone-100 pb-3 dark:border-stone-800">
                                <span className="text-stone-500">Location</span>
                                <span className="font-medium">
                                    {location.name}
                                </span>
                            </div>
                            <div className="flex items-center justify-between border-b border-stone-100 pb-3 dark:border-stone-800">
                                <span className="text-stone-500">Address</span>
                                <span className="font-medium">
                                    {location.address}
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-stone-500">
                                    Max Storage
                                </span>
                                <span className="font-medium">
                                    {location.max_storage.toLocaleString()}{' '}
                                    units
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Recommendations */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <TrendingUp className="h-5 w-5 text-emerald-500" />
                            Recommendations
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {quantity === 0 && (
                                <div className="rounded-lg border border-rose-200 bg-rose-50 p-4 dark:border-rose-800 dark:bg-rose-950">
                                    <p className="font-medium text-rose-700 dark:text-rose-300">
                                        Immediate Action Required
                                    </p>
                                    <p className="text-sm text-rose-600 dark:text-rose-400">
                                        This item is out of stock. Place an
                                        emergency order to avoid service
                                        disruptions.
                                    </p>
                                </div>
                            )}
                            {quantity > 0 && quantity < 50 && (
                                <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950">
                                    <p className="font-medium text-amber-700 dark:text-amber-300">
                                        Reorder Soon
                                    </p>
                                    <p className="text-sm text-amber-600 dark:text-amber-400">
                                        Stock is running low. Consider placing
                                        an order within the next 1-2 days.
                                    </p>
                                </div>
                            )}
                            {quantity >= 50 && (
                                <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-950">
                                    <p className="font-medium text-emerald-700 dark:text-emerald-300">
                                        Stock Healthy
                                    </p>
                                    <p className="text-sm text-emerald-600 dark:text-emerald-400">
                                        Current stock levels are adequate.
                                        Monitor for any demand spikes.
                                    </p>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </GameLayout>
    );
}
