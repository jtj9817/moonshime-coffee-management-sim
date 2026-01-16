import { Head } from '@inertiajs/react';
import { ArrowRight, ArrowRightLeft, MapPin, Plus, Truck } from 'lucide-react';

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
import { TransferModel, type BreadcrumbItem } from '@/types';

interface TransfersProps {
    transfers: TransferModel[];
    suggestions: Array<{
        from: string;
        to: string;
        product: string;
        quantity: number;
    }>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mission Control', href: '/game/dashboard' },
    { title: 'Logistics', href: '/game/transfers' },
];

function getStatusBadge(status: string) {
    switch (status) {
        case 'draft':
            return <Badge variant="outline">Draft</Badge>;
        case 'in_transit':
            return <Badge className="bg-blue-500">In Transit</Badge>;
        case 'completed':
            return <Badge className="bg-emerald-500">Completed</Badge>;
        case 'cancelled':
            return <Badge variant="destructive">Cancelled</Badge>;
        default:
            return <Badge variant="outline">{status}</Badge>;
    }
}

export default function Transfers({ transfers, suggestions }: TransfersProps) {
    return (
        <GameLayout breadcrumbs={breadcrumbs}>
            <Head title="Logistics" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-stone-900 dark:text-white">
                            Logistics Hub
                        </h1>
                        <p className="text-stone-500 dark:text-stone-400">
                            Manage inter-location transfers
                        </p>
                    </div>
                    <Button className="gap-2 bg-amber-600 hover:bg-amber-700">
                        <Plus className="h-4 w-4" />
                        New Transfer
                    </Button>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-stone-500">
                                In Transit
                            </CardTitle>
                            <Truck className="h-4 w-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {transfers.filter((t) => t.status === 'in_transit').length}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-stone-500">
                                Completed Today
                            </CardTitle>
                            <ArrowRightLeft className="h-4 w-4 text-emerald-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {transfers.filter((t) => t.status === 'completed').length}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-stone-500">
                                Suggestions
                            </CardTitle>
                            <MapPin className="h-4 w-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{suggestions.length}</div>
                        </CardContent>
                    </Card>
                </div>

                {/* Transfers Table */}
                <div className="rounded-xl border border-stone-200 bg-white dark:border-stone-700 dark:bg-stone-800">
                    <div className="border-b border-stone-200 p-4 dark:border-stone-700">
                        <h2 className="font-semibold text-stone-900 dark:text-white">
                            Transfer History
                        </h2>
                    </div>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Transfer ID</TableHead>
                                <TableHead>Route</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>ETA</TableHead>
                                <TableHead>Created</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {transfers.map((transfer) => (
                                <TableRow key={transfer.id}>
                                    <TableCell className="font-mono text-sm">
                                        {transfer.id.substring(0, 8)}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">
                                                {transfer.sourceLocation?.name ?? 'Unknown'}
                                            </span>
                                            <ArrowRight className="h-4 w-4 text-stone-400" />
                                            <span className="font-medium">
                                                {transfer.targetLocation?.name ?? 'Unknown'}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell>{getStatusBadge(transfer.status)}</TableCell>
                                    <TableCell>
                                        {transfer.delivery_day
                                            ? `Day ${transfer.delivery_day}`
                                            : '-'}
                                    </TableCell>
                                    <TableCell className="text-stone-500">
                                        {new Date(transfer.created_at).toLocaleDateString()}
                                    </TableCell>
                                </TableRow>
                            ))}
                            {transfers.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="py-12 text-center text-stone-500"
                                    >
                                        No transfers yet
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </GameLayout>
    );
}
