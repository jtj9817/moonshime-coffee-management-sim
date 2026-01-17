import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Package } from 'lucide-react';
import { type ReactNode } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useGame } from '@/contexts/game-context';
import GameLayout from '@/layouts/game-layout';
import { InventoryModel, type BreadcrumbItem } from '@/types';

interface InventoryProps {
    inventory: InventoryModel[];
    currentLocation: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mission Control', href: '/game/dashboard' },
    { title: 'Pantry', href: '/game/inventory' },
];

function getStockStatus(quantity: number) {
    if (quantity === 0) return { label: 'Out of Stock', color: 'destructive' as const, icon: AlertTriangle };
    if (quantity < 50) return { label: 'Low Stock', color: 'secondary' as const, icon: AlertTriangle };
    return { label: 'In Stock', color: 'default' as const, icon: CheckCircle2 };
}

export default function Inventory({ inventory, currentLocation }: InventoryProps) {
    const { locations, setCurrentLocationId } = useGame();

    const handleLocationChange = (value: string) => {
        setCurrentLocationId(value);
        window.location.href = `/game/inventory?location=${value}`;
    };

    return (
        <>
            <Head title="Inventory" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-stone-900 dark:text-white">
                            Pantry Inventory
                        </h1>
                        <p className="text-stone-500 dark:text-stone-400">
                            Track and manage stock across all locations
                        </p>
                    </div>
                    <Select value={currentLocation} onValueChange={handleLocationChange}>
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="Select location" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Locations</SelectItem>
                            {locations.map((location) => (
                                <SelectItem key={location.id} value={location.id}>
                                    {location.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {/* Inventory Table */}
                <div className="rounded-xl border border-stone-200 bg-white dark:border-stone-700 dark:bg-stone-800">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Product</TableHead>
                                <TableHead>Category</TableHead>
                                <TableHead>Location</TableHead>
                                <TableHead className="text-right">Quantity</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {inventory.map((item) => {
                                const status = getStockStatus(item.quantity);
                                const StatusIcon = status.icon;
                                return (
                                    <TableRow key={item.id}>
                                        <TableCell className="font-medium">
                                            <div className="flex items-center gap-2">
                                                <Package className="h-4 w-4 text-stone-400" />
                                                {item.product?.name ?? 'Unknown Product'}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {item.product?.category ?? 'Unknown'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>{item.location?.name ?? 'Unknown'}</TableCell>
                                        <TableCell className="text-right font-mono">
                                            {item.quantity}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={status.color} className="gap-1">
                                                <StatusIcon className="h-3 w-3" />
                                                {status.label}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Link
                                                href={`/game/sku/${item.location_id}/${item.product_id}`}
                                            >
                                                <Button variant="ghost" size="sm">
                                                    View
                                                </Button>
                                            </Link>
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                            {inventory.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="py-12 text-center text-stone-500"
                                    >
                                        No inventory items found
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

Inventory.layout = (page: ReactNode) => (
    <GameLayout breadcrumbs={breadcrumbs}>{page}</GameLayout>
);
