import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    Clock,
    DollarSign,
    ExternalLink,
    PartyPopper,
    Shield,
    Timer,
    Zap,
} from 'lucide-react';
import { useEffect, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { SpikeEventModel, type BreadcrumbItem } from '@/types';

interface SpikeHistoryProps {
    spikes: SpikeEventModel[];
    activeSpikes: SpikeEventModel[];
    statistics: {
        totalSpikes: number;
        activeSpikes: number;
        resolvedSpikes: number;
        resolvedByPlayer: number;
    };
    currentDay: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mission Control', href: '/game/dashboard' },
    { title: 'War Room', href: '/game/spike-history' },
];

function getSpikeTypeBadge(type: string) {
    switch (type.toLowerCase()) {
        case 'demand':
            return <Badge className="bg-amber-500 hover:bg-amber-600">Demand Surge</Badge>;
        case 'delay':
            return <Badge className="bg-blue-500 hover:bg-blue-600">Delivery Delay</Badge>;
        case 'price':
            return <Badge className="bg-rose-500 hover:bg-rose-600">Price Spike</Badge>;
        case 'breakdown':
            return <Badge variant="destructive">Equipment Breakdown</Badge>;
        case 'blizzard':
            return <Badge className="bg-sky-600 hover:bg-sky-700">Blizzard Warning</Badge>;
        default:
            return <Badge variant="outline">{type}</Badge>;
    }
}

function getSpikeTypeIcon(type: string) {
    switch (type.toLowerCase()) {
        case 'demand':
            return <Zap className="h-5 w-5 text-amber-500" />;
        case 'delay':
            return <Clock className="h-5 w-5 text-blue-500" />;
        case 'price':
            return <DollarSign className="h-5 w-5 text-rose-500" />;
        case 'breakdown':
            return <AlertTriangle className="h-5 w-5 text-red-500" />;
        case 'blizzard':
            return <Shield className="h-5 w-5 text-sky-600" />;
        default:
            return <Activity className="h-5 w-5 text-stone-500" />;
    }
}

function getResolutionStatus(spike: SpikeEventModel) {
    if (spike.is_active) {
        return (
            <Badge variant="destructive" className="animate-pulse">
                Active
            </Badge>
        );
    }
    if (spike.resolved_by === 'player') {
        return <Badge className="bg-emerald-500">Resolved by Player</Badge>;
    }
    if (spike.resolved_by === 'time') {
        return <Badge variant="secondary">Expired</Badge>;
    }
    return <Badge variant="outline">Resolved</Badge>;
}

interface ActiveEventCardProps {
    spike: SpikeEventModel;
    currentDay: number;
    onResolve: (spike: SpikeEventModel) => void;
}

function ActiveEventCard({ spike, currentDay, onResolve }: ActiveEventCardProps) {
    const daysRemaining = spike.ends_at_day - currentDay;

    return (
        <Card className="border-2 border-rose-500/30 bg-gradient-to-br from-rose-50 to-white dark:from-rose-950/30 dark:to-stone-900">
            <CardHeader className="pb-3">
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-3">
                        <div className="rounded-lg bg-rose-100 p-2 dark:bg-rose-900/50">
                            {getSpikeTypeIcon(spike.type)}
                        </div>
                        <div>
                            <CardTitle className="text-lg">{spike.name}</CardTitle>
                            {getSpikeTypeBadge(spike.type)}
                        </div>
                    </div>
                    <div className="flex items-center gap-1 rounded-full bg-rose-100 px-3 py-1 text-sm font-bold text-rose-700 dark:bg-rose-900/50 dark:text-rose-300">
                        <Timer className="h-4 w-4" />
                        {daysRemaining > 0 ? `${daysRemaining} day${daysRemaining > 1 ? 's' : ''} left` : 'Ends today'}
                    </div>
                </div>
                <CardDescription className="mt-2 text-stone-600 dark:text-stone-400">
                    {spike.description}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Playbook Actions */}
                {spike.playbook && spike.playbook.actions.length > 0 && (
                    <div>
                        <h4 className="mb-2 text-xs font-bold uppercase tracking-wider text-stone-500">
                            Playbook Actions
                        </h4>
                        <div className="flex flex-wrap gap-2">
                            {spike.playbook.actions.map((action, idx) => (
                                <Link key={idx} href={action.href}>
                                    <Button variant="outline" size="sm" className="gap-1">
                                        {action.label}
                                        <ExternalLink className="h-3 w-3" />
                                    </Button>
                                </Link>
                            ))}
                        </div>
                    </div>
                )}

                {/* Resolve Early Button */}
                {spike.playbook?.canResolveEarly && (
                    <div className="rounded-lg border-2 border-dashed border-emerald-500/30 bg-emerald-50/50 p-3 dark:bg-emerald-900/20">
                        <div className="flex items-center justify-between">
                            <div>
                                <h4 className="font-bold text-emerald-700 dark:text-emerald-300">
                                    Early Resolution Available
                                </h4>
                                <p className="text-sm text-emerald-600 dark:text-emerald-400">
                                    Pay to resolve immediately and restore normal operations
                                </p>
                            </div>
                            <Button
                                onClick={() => onResolve(spike)}
                                className="gap-2 bg-emerald-600 hover:bg-emerald-700"
                            >
                                Resolve
                                <span className="font-mono">
                                    ${formatCurrency(spike.playbook.resolutionCost / 100)}
                                </span>
                            </Button>
                        </div>
                    </div>
                )}

                {/* Info for non-resolvable spikes */}
                {spike.playbook && !spike.playbook.canResolveEarly && (
                    <p className="text-xs text-stone-500 dark:text-stone-400">
                        <em>This event cannot be resolved early. Use the playbook actions to mitigate its effects.</em>
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

export default function SpikeHistory({ spikes, activeSpikes, statistics, currentDay }: SpikeHistoryProps) {
    const [resolving, setResolving] = useState<SpikeEventModel | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [showSuccessAnimation, setShowSuccessAnimation] = useState(false);

    const handleResolve = (spike: SpikeEventModel) => {
        setResolving(spike);
    };

    const confirmResolve = () => {
        if (!resolving) return;
        setIsSubmitting(true);
        router.post(`/game/spikes/${resolving.id}/resolve`, {}, {
            onSuccess: () => {
                setResolving(null);
                setIsSubmitting(false);
                setShowSuccessAnimation(true);
            },
            onError: () => {
                setIsSubmitting(false);
            },
        });
    };

    // Auto-hide success animation after 3 seconds
    useEffect(() => {
        if (showSuccessAnimation) {
            const timer = setTimeout(() => setShowSuccessAnimation(false), 3000);
            return () => clearTimeout(timer);
        }
    }, [showSuccessAnimation]);

    return (
        <GameLayout breadcrumbs={breadcrumbs}>
            <Head title="War Room" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-stone-900 dark:text-white">
                            War Room - Spike Command Center
                        </h1>
                        <p className="text-stone-500 dark:text-stone-400">
                            Monitor, respond to, and resolve market disruptions
                        </p>
                    </div>
                    <Badge variant="outline" className="text-sm">
                        Day {currentDay}
                    </Badge>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
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
                                Expired
                            </CardTitle>
                            <Timer className="h-4 w-4 text-stone-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-stone-600">
                                {statistics.resolvedSpikes - statistics.resolvedByPlayer}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-stone-500">
                                Resolved by You
                            </CardTitle>
                            <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-emerald-600">
                                {statistics.resolvedByPlayer}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Active Spikes Section */}
                {activeSpikes.length > 0 && (
                    <div>
                        <div className="mb-4 flex items-center gap-3">
                            <AlertTriangle className="h-6 w-6 text-rose-500" />
                            <h2 className="text-xl font-bold text-stone-900 dark:text-white">
                                Active Events ({activeSpikes.length})
                            </h2>
                        </div>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            {activeSpikes.map((spike) => (
                                <ActiveEventCard
                                    key={spike.id}
                                    spike={spike}
                                    currentDay={currentDay}
                                    onResolve={handleResolve}
                                />
                            ))}
                        </div>
                    </div>
                )}

                {/* No Active Spikes */}
                {activeSpikes.length === 0 && (
                    <div className="flex items-center gap-3 rounded-xl border-2 border-emerald-500 bg-emerald-50 p-4 dark:border-emerald-600 dark:bg-emerald-950">
                        <CheckCircle2 className="h-6 w-6 text-emerald-500" />
                        <div>
                            <h3 className="font-bold text-emerald-700 dark:text-emerald-300">
                                All Clear
                            </h3>
                            <p className="text-sm text-emerald-600 dark:text-emerald-400">
                                No active disruptions. Operations running smoothly.
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
                                <TableHead>Duration</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Resolution Cost</TableHead>
                                <TableHead>Date</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {spikes.map((spike) => (
                                <TableRow key={spike.id}>
                                    <TableCell className="font-medium">{spike.name}</TableCell>
                                    <TableCell>{getSpikeTypeBadge(spike.type)}</TableCell>
                                    <TableCell className="text-stone-500">
                                        Day {spike.starts_at_day} → {spike.ends_at_day}
                                    </TableCell>
                                    <TableCell>{getResolutionStatus(spike)}</TableCell>
                                    <TableCell className="text-stone-500">
                                        {spike.resolution_cost
                                            ? `$${formatCurrency(spike.resolution_cost / 100)}`
                                            : '—'}
                                    </TableCell>
                                    <TableCell className="text-stone-500">
                                        {new Date(spike.created_at).toLocaleDateString()}
                                    </TableCell>
                                </TableRow>
                            ))}
                            {spikes.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
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

            {/* Success Animation Overlay */}
            {showSuccessAnimation && (
                <div className="pointer-events-none fixed inset-0 z-50 flex items-center justify-center">
                    <div className="animate-bounce rounded-full bg-emerald-500 p-6 shadow-2xl">
                        <PartyPopper className="h-12 w-12 text-white" />
                    </div>
                </div>
            )}

            {/* Resolve Confirmation Dialog */}
            <Dialog open={!!resolving} onOpenChange={() => setResolving(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirm Early Resolution</DialogTitle>
                        <DialogDescription>
                            You are about to resolve the <strong>{resolving?.name}</strong> event early.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="py-4">
                        <div className="rounded-lg bg-stone-100 p-4 dark:bg-stone-800">
                            <div className="flex items-center justify-between">
                                <span className="text-stone-600 dark:text-stone-400">Resolution Cost</span>
                                <span className="text-xl font-bold text-stone-900 dark:text-white">
                                    ${formatCurrency((resolving?.playbook?.resolutionCost ?? 0) / 100)}
                                </span>
                            </div>
                        </div>
                        <p className="mt-3 text-sm text-stone-500">
                            This will deduct the amount from your cash and immediately restore normal operations.
                        </p>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setResolving(null)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={confirmResolve}
                            disabled={isSubmitting}
                            className="bg-emerald-600 hover:bg-emerald-700"
                        >
                            {isSubmitting ? 'Resolving...' : 'Confirm & Pay'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </GameLayout>
    );
}
