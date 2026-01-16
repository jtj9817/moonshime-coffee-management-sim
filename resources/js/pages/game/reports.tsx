import { Head } from '@inertiajs/react';
import { AlertTriangle, PieChart, Trash2 } from 'lucide-react';

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
import { type BreadcrumbItem } from '@/types';

interface WasteEvent {
    id: string;
    product: string;
    location: string;
    quantity: number;
    cause: string;
    value: number;
    date: string;
}

interface ReportsProps {
    wasteEvents: WasteEvent[];
    wasteByCause: Array<{ cause: string; amount: number }>;
    wasteByLocation: Array<{ location: string; amount: number }>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mission Control', href: '/game/dashboard' },
    { title: 'Wastage', href: '/game/reports' },
];

export default function Reports({
    wasteEvents,
    wasteByCause,
    wasteByLocation,
}: ReportsProps) {
    const totalWasteValue = wasteEvents.reduce((sum, e) => sum + e.value, 0);
    const totalWasteQuantity = wasteEvents.reduce((sum, e) => sum + e.quantity, 0);

    return (
        <GameLayout breadcrumbs={breadcrumbs}>
            <Head title="Wastage Reports" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-stone-900 dark:text-white">
                        Wastage Reports
                    </h1>
                    <p className="text-stone-500 dark:text-stone-400">
                        Track and analyze inventory losses
                    </p>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-stone-500">
                                Total Waste Value
                            </CardTitle>
                            <Trash2 className="h-4 w-4 text-rose-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-rose-600">
                                ${totalWasteValue.toLocaleString()}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-stone-500">
                                Units Lost
                            </CardTitle>
                            <AlertTriangle className="h-4 w-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{totalWasteQuantity}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-stone-500">
                                Waste Events
                            </CardTitle>
                            <PieChart className="h-4 w-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{wasteEvents.length}</div>
                        </CardContent>
                    </Card>
                </div>

                {/* Charts Grid */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Waste by Cause */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Waste by Cause</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {wasteByCause.length > 0 ? (
                                <div className="space-y-4">
                                    {wasteByCause.map((item, i) => (
                                        <div key={i}>
                                            <div className="mb-1 flex items-center justify-between text-sm">
                                                <span className="font-medium capitalize">
                                                    {item.cause}
                                                </span>
                                                <span className="text-stone-500">
                                                    ${item.amount.toLocaleString()}
                                                </span>
                                            </div>
                                            <div className="h-2 w-full overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                                                <div
                                                    className="h-full bg-rose-500"
                                                    style={{
                                                        width: `${totalWasteValue > 0 ? (item.amount / totalWasteValue) * 100 : 0}%`,
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="py-8 text-center text-stone-500">
                                    No waste data yet
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Waste by Location */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Waste by Location</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {wasteByLocation.length > 0 ? (
                                <div className="space-y-4">
                                    {wasteByLocation.map((item, i) => (
                                        <div key={i}>
                                            <div className="mb-1 flex items-center justify-between text-sm">
                                                <span className="font-medium">{item.location}</span>
                                                <span className="text-stone-500">
                                                    ${item.amount.toLocaleString()}
                                                </span>
                                            </div>
                                            <div className="h-2 w-full overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                                                <div
                                                    className="h-full bg-amber-500"
                                                    style={{
                                                        width: `${totalWasteValue > 0 ? (item.amount / totalWasteValue) * 100 : 0}%`,
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="py-8 text-center text-stone-500">
                                    No waste data yet
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Waste Events Table */}
                <div className="rounded-xl border border-stone-200 bg-white dark:border-stone-700 dark:bg-stone-800">
                    <div className="border-b border-stone-200 p-4 dark:border-stone-700">
                        <h2 className="font-semibold text-stone-900 dark:text-white">
                            Recent Waste Events
                        </h2>
                    </div>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Product</TableHead>
                                <TableHead>Location</TableHead>
                                <TableHead>Cause</TableHead>
                                <TableHead className="text-right">Quantity</TableHead>
                                <TableHead className="text-right">Value</TableHead>
                                <TableHead>Date</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {wasteEvents.map((event) => (
                                <TableRow key={event.id}>
                                    <TableCell className="font-medium">{event.product}</TableCell>
                                    <TableCell>{event.location}</TableCell>
                                    <TableCell className="capitalize">{event.cause}</TableCell>
                                    <TableCell className="text-right">{event.quantity}</TableCell>
                                    <TableCell className="text-right text-rose-600">
                                        ${event.value.toLocaleString()}
                                    </TableCell>
                                    <TableCell className="text-stone-500">{event.date}</TableCell>
                                </TableRow>
                            ))}
                            {wasteEvents.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="py-12 text-center text-stone-500"
                                    >
                                        <Trash2 className="mx-auto mb-2 h-8 w-8 text-stone-400" />
                                        No waste events recorded yet
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
