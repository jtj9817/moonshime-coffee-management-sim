import { Head } from '@inertiajs/react';
import { Clock, Package, Plus, ShoppingCart, Truck, XCircle } from 'lucide-react';
import { useState } from 'react';

import { CancelOrderDialog } from '@/components/game/cancel-order-dialog';
import { NewOrderDialog } from '@/components/game/new-order-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import GameLayout from '@/layouts/game-layout';
import { formatCurrency } from '@/lib/formatCurrency';
import { OrderModel, type BreadcrumbItem } from '@/types';

interface OrderingProps {
    orders: OrderModel[];
    vendorProducts: Array<{
        vendor: { id: string; name: string; reliability_score: number };
        products: Array<{ id: string; name: string; category: string }>;
    }>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mission Control', href: '/game/dashboard' },
    { title: 'Procurement', href: '/game/ordering' },
];

function getStatusBadge(status: string) {
    switch (status.toLowerCase()) {
        case 'draft':
            return <Badge variant="outline">Draft</Badge>;
        case 'pending':
            return <Badge variant="secondary">Pending</Badge>;
        case 'shipped':
            return <Badge className="bg-blue-500 text-white">Shipped</Badge>;
        case 'delivered':
            return <Badge className="bg-emerald-500 text-white">Delivered</Badge>;
        case 'cancelled':
            return <Badge variant="destructive">Cancelled</Badge>;
        default:
            return <Badge variant="outline">{status}</Badge>;
    }
}

export default function Ordering({ orders, vendorProducts }: OrderingProps) {
    const [isNewOrderDialogOpen, setIsNewOrderDialogOpen] = useState(false);
    const [selectedOrderToCancel, setSelectedOrderToCancel] = useState<OrderModel | null>(null);

    const handleCancelOrder = (order: OrderModel) => {
        setSelectedOrderToCancel(order);
    };

    return (
        <GameLayout breadcrumbs={breadcrumbs}>
            <Head title="Procurement" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-stone-900 dark:text-white">
                            Procurement Center
                        </h1>
                        <p className="text-stone-500 dark:text-stone-400">
                            Manage supplier orders and deliveries
                        </p>
                    </div>
                    <Button
                        onClick={() => setIsNewOrderDialogOpen(true)}
                        className="gap-2 bg-amber-600 hover:bg-amber-700"
                    >
                        <Plus className="h-4 w-4" />
                        New Order
                    </Button>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-stone-500">
                                Pending Orders
                            </CardTitle>
                            <Clock className="h-4 w-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {orders.filter((o) => o.status.toLowerCase().includes('pending')).length}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-stone-500">
                                In Transit
                            </CardTitle>
                            <Truck className="h-4 w-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {orders.filter((o) => o.status.toLowerCase().includes('shipped')).length}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-stone-500">
                                Available Suppliers
                            </CardTitle>
                            <ShoppingCart className="h-4 w-4 text-emerald-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{vendorProducts.length}</div>
                        </CardContent>
                    </Card>
                </div>

                {/* Orders Table */}
                <div className="rounded-xl border border-stone-200 bg-white dark:border-stone-700 dark:bg-stone-800 overflow-hidden">
                    <div className="border-b border-stone-200 p-4 dark:border-stone-700">
                        <h2 className="font-semibold text-stone-900 dark:text-white">
                            Recent Orders
                        </h2>
                    </div>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Order ID</TableHead>
                                <TableHead>Vendor</TableHead>
                                <TableHead>Items</TableHead>
                                <TableHead className="text-right">Total</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Delivery</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {orders.map((order) => {
                                const status = order.status.toLowerCase();
                                const isCancellable = status === 'shipped';

                                return (
                                    <TableRow key={order.id}>
                                        <TableCell className="font-mono text-sm">
                                            {order.id.substring(0, 8)}
                                        </TableCell>
                                        <TableCell>{order.vendor?.name ?? 'Unknown'}</TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-1">
                                                <Package className="h-4 w-4 text-stone-400" />
                                                {order.items?.length ?? 0} items
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-right font-mono">
                                            ${formatCurrency(order.total_cost)}
                                        </TableCell>
                                        <TableCell>{getStatusBadge(order.status)}</TableCell>
                                        <TableCell>
                                            {order.delivery_day
                                                ? `Day ${order.delivery_day}`
                                                : 'Pending'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {isCancellable && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => handleCancelOrder(order)}
                                                    className="h-8 gap-1 text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:text-rose-400 dark:hover:bg-rose-900/20"
                                                >
                                                    <XCircle className="h-3.5 w-3.5" />
                                                    Cancel
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                            {orders.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
                                        className="h-64 text-center"
                                    >
                                        <div className="flex flex-col items-center justify-center gap-4">
                                            <div className="rounded-full bg-stone-100 p-4 dark:bg-stone-900">
                                                <Package className="h-8 w-8 text-stone-400" />
                                            </div>
                                            <div className="space-y-1">
                                                <h3 className="font-semibold text-stone-900 dark:text-white">
                                                    No active orders
                                                </h3>
                                                <p className="text-sm text-stone-500 max-w-xs mx-auto">
                                                    Your supply chain is quiet. Start by placing a purchase order from one of our vendors.
                                                </p>
                                            </div>
                                            <Button
                                                onClick={() => setIsNewOrderDialogOpen(true)}
                                                className="mt-2 bg-amber-600 hover:bg-amber-700 text-white gap-2"
                                            >
                                                <Plus className="h-4 w-4" />
                                                Place First Order
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            <NewOrderDialog
                open={isNewOrderDialogOpen}
                onOpenChange={setIsNewOrderDialogOpen}
                vendorProducts={vendorProducts}
            />

            <CancelOrderDialog
                order={selectedOrderToCancel}
                open={!!selectedOrderToCancel}
                onOpenChange={(open) => !open && setSelectedOrderToCancel(null)}
            />
        </GameLayout>
    );
}
