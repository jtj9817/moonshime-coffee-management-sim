import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Package, ShoppingCart, Star, TrendingUp, Truck } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatCurrency } from '@/lib/formatCurrency';
import GameLayout from '@/layouts/game-layout';
import { OrderModel, ProductModel, VendorModel, type BreadcrumbItem } from '@/types';

interface VendorDetailProps {
    vendor: VendorModel & {
        products?: ProductModel[];
        orders?: OrderModel[];
    };
    metrics: {
        totalOrders: number;
        totalSpent: number;
        avgDeliveryTime: number;
        onTimeDeliveryRate: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mission Control', href: '/game/dashboard' },
    { title: 'Suppliers', href: '/game/vendors' },
    { title: 'Details', href: '#' },
];

function getReliabilityColor(score: number) {
    if (score >= 90) return 'text-emerald-600';
    if (score >= 70) return 'text-amber-600';
    return 'text-rose-600';
}

export default function VendorDetail({ vendor, metrics }: VendorDetailProps) {
    return (
        <GameLayout breadcrumbs={breadcrumbs}>
            <Head title={`Supplier - ${vendor.name}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-4">
                        <Link href="/game/vendors">
                            <Button variant="ghost" size="icon">
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold text-stone-900 dark:text-white">
                                {vendor.name}
                            </h1>
                            <p className="text-stone-500 dark:text-stone-400">
                                Supplier Details & Performance
                            </p>
                        </div>
                    </div>
                    <Button className="gap-2 bg-amber-600 hover:bg-amber-700">
                        <ShoppingCart className="h-4 w-4" />
                        New Order
                    </Button>
                </div>

                {/* Reliability Score */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Star className="h-5 w-5 text-amber-500" />
                            Reliability Score
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center gap-6">
                            <div
                                className={`text-5xl font-bold ${getReliabilityColor(vendor.reliability_score)}`}
                            >
                                {vendor.reliability_score}%
                            </div>
                            <div className="flex-1">
                                <Progress value={vendor.reliability_score} className="h-3" />
                                <p className="mt-2 text-sm text-stone-500">
                                    {vendor.reliability_score >= 90
                                        ? 'Excellent - Highly reliable supplier'
                                        : vendor.reliability_score >= 70
                                          ? 'Good - Generally reliable with occasional issues'
                                          : 'Needs Improvement - Consider alternative suppliers'}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Metrics */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-stone-500">
                                Total Orders
                            </CardTitle>
                            <ShoppingCart className="h-4 w-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{metrics.totalOrders}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-stone-500">
                                Total Spent
                            </CardTitle>
                            <TrendingUp className="h-4 w-4 text-emerald-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                ${formatCurrency(metrics.totalSpent)}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-stone-500">
                                Avg Delivery
                            </CardTitle>
                            <Truck className="h-4 w-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {metrics.avgDeliveryTime} days
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-stone-500">
                                On-Time Rate
                            </CardTitle>
                            <Star className="h-4 w-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {metrics.onTimeDeliveryRate}%
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Products & Orders */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Available Products */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Package className="h-5 w-5 text-amber-500" />
                                Available Products
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {vendor.products && vendor.products.length > 0 ? (
                                <div className="space-y-2">
                                    {vendor.products.map((product) => (
                                        <div
                                            key={product.id}
                                            className="flex items-center justify-between rounded-lg border border-stone-200 p-3 dark:border-stone-700"
                                        >
                                            <div>
                                                <p className="font-medium">{product.name}</p>
                                                <p className="text-sm text-stone-500">
                                                    {product.category}
                                                </p>
                                            </div>
                                            <Badge variant="outline">In Stock</Badge>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="py-8 text-center text-stone-500">
                                    No products available
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Recent Orders */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <ShoppingCart className="h-5 w-5 text-amber-500" />
                                Recent Orders
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {vendor.orders && vendor.orders.length > 0 ? (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Order ID</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">Total</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {vendor.orders.slice(0, 5).map((order) => (
                                            <TableRow key={order.id}>
                                                <TableCell className="font-mono text-sm">
                                                    {order.id.substring(0, 8)}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">{order.status}</Badge>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    ${formatCurrency(order.total_cost)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            ) : (
                                <p className="py-8 text-center text-stone-500">
                                    No orders yet
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </GameLayout>
    );
}
