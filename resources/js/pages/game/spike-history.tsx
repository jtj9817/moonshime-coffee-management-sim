import { Head } from '@inertiajs/react';
import { Activity, AlertTriangle, CheckCircle2, Zap } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
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
import { SpikeEventModel, type BreadcrumbItem } from '@/types';

interface SpikeHistoryProps {
    spikes: SpikeEventModel[];
    statistics: {
        totalSpikes: number;
        activeSpikes: number;
        resolvedSpikes: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mission Control', href: '/game/dashboard' },
    { title: 'War Room', href: '/game/spike-history' },
];

function getSpikeTypeBadge(type: string) {
    switch (type.toLowerCase()) {
        case 'demand':
            return <Badge className="bg-amber-500">Demand Surge</Badge>;
        case 'delay':
            return <Badge className="bg-blue-500">Delivery Delay</Badge>;
        case 'price':
            return <Badge className="bg-rose-500">Price Spike</Badge>;
        case 'breakdown':
            return <Badge variant="destructive">Equipment Breakdown</Badge>;
        default:
            return <Badge variant="outline">{type}</Badge>;
    }
}

export default function SpikeHistory({ spikes, statistics }: SpikeHistoryProps) {
    return (
        <GameLayout breadcrumbs={breadcrumbs}>
            <Head title="War Room" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-stone-900 dark:text-white">
                        War Room - Spike Monitor
                    </h1>
                    <p className="text-stone-500 dark:text-stone-400">
                        Track and respond to market disruptions
                    </p>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-stone-500">
                                Total Events
                            </CardTitle>
                            <Activity className="h-4 w-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{statistics.totalSpikes}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-stone-500">
                                Active
                            </CardTitle>
                            <Zap className="h-4 w-4 text-rose-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-rose-600">
                                {statistics.activeSpikes}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-stone-500">
                                Resolved
                            </CardTitle>
                            <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-emerald-600">
                                {statistics.resolvedSpikes}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Active Spikes Alert */}
                {statistics.activeSpikes > 0 && (
                    <div className="flex items-center gap-3 rounded-xl border-2 border-rose-500 bg-rose-50 p-4 dark:border-rose-600 dark:bg-rose-950">
                        <AlertTriangle className="h-6 w-6 text-rose-500" />
                        <div>
                            <h3 className="font-bold text-rose-700 dark:text-rose-300">
                                {statistics.activeSpikes} Active Spike
                                {statistics.activeSpikes > 1 ? 's' : ''}
                            </h3>
                            <p className="text-sm text-rose-600 dark:text-rose-400">
                                Immediate attention required to prevent losses
                            </p>
                        </div>
                    </div>
                )}

                {/* Spike History Table */}
                <div className="rounded-xl border border-stone-200 bg-white dark:border-stone-700 dark:bg-stone-800">
                    <div className="border-b border-stone-200 p-4 dark:border-stone-700">
                        <h2 className="font-semibold text-stone-900 dark:text-white">
                            Event History
                        </h2>
                    </div>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Event</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Date</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {spikes.map((spike) => (
                                <TableRow key={spike.id}>
                                    <TableCell className="font-medium">{spike.name}</TableCell>
                                    <TableCell>{getSpikeTypeBadge(spike.type)}</TableCell>
                                    <TableCell className="max-w-xs truncate text-stone-500">
                                        {spike.description}
                                    </TableCell>
                                    <TableCell>
                                        {spike.is_active ? (
                                            <Badge variant="destructive" className="animate-pulse">
                                                Active
                                            </Badge>
                                        ) : (
                                            <Badge className="bg-emerald-500">Resolved</Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-stone-500">
                                        {new Date(spike.created_at).toLocaleDateString()}
                                    </TableCell>
                                </TableRow>
                            ))}
                            {spikes.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="py-12 text-center text-stone-500"
                                    >
                                        <Activity className="mx-auto mb-2 h-8 w-8 text-stone-400" />
                                        No spike events recorded yet
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
