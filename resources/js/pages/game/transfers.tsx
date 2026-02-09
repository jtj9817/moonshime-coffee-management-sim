import { Head, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    ArrowRightLeft,
    Info,
    MapPin,
    Plus,
    Truck,
} from 'lucide-react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { formatCurrency } from '@/lib/formatCurrency';
import game from '@/routes/game';
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
        case 'pending':
            return <Badge variant="secondary">Pending</Badge>;
        case 'in_transit':
            return (
                <Badge variant="default" className="bg-blue-500">
                    In Transit
                </Badge>
            );
        case 'completed':
            return (
                <Badge variant="default" className="bg-emerald-500">
                    Completed
                </Badge>
            );
        case 'cancelled':
            return <Badge variant="destructive">Cancelled</Badge>;
        default:
            return <Badge variant="outline">{status}</Badge>;
    }
}

export default function Transfers({ transfers, suggestions }: TransfersProps) {
    const { locations, products } = useGame();
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [routeInfo, setRouteInfo] = useState<{
        reachable: boolean;
        path?: Array<{
            id: number;
            source: string;
            target: string;
            transport_mode: string;
            cost: number;
            is_premium: boolean;
        }>;
        total_cost?: number;
        message?: string;
    } | null>(null);
    const [loadingRoute, setLoadingRoute] = useState(false);
    const [showAlternative, setShowAlternative] = useState(false);

    const locationNameById = useMemo(() => {
        const map = new Map<string, string>();
        locations.forEach((location) => {
            map.set(location.id, location.name);
        });
        return map;
    }, [locations]);

    const getLocationName = (locationId?: string, fallbackName?: string) => {
        if (fallbackName) {
            return fallbackName;
        }
        if (!locationId) {
            return 'Unknown';
        }
        return locationNameById.get(locationId) ?? 'Unknown';
    };

    const { data, setData, post, processing, reset } = useForm({
        source_location_id: '',
        target_location_id: '',
        items: [{ product_id: '', quantity: 1 }],
    });

    // Determine if the current path contains any premium routes
    const hasPremiumRoute = routeInfo?.path?.some((step) => step.is_premium);

    const shouldFetchRoute = !!(
        data.source_location_id &&
        data.target_location_id &&
        data.source_location_id !== data.target_location_id
    );

    // Reset route info when inputs become invalid (derived state)
    if (!shouldFetchRoute && routeInfo !== null) {
        setRouteInfo(null);
        setShowAlternative(false);
    }

    useEffect(() => {
        if (!shouldFetchRoute) return;

        let cancelled = false;
        const controller = new AbortController();

        fetch(
            `/game/logistics/path?source_id=${data.source_location_id}&target_id=${data.target_location_id}`,
            { signal: controller.signal },
        )
            .then((res) => res.json())
            .then((result) => {
                if (!cancelled) {
                    setRouteInfo(result);
                    setShowAlternative(false);
                    setLoadingRoute(false);
                }
            })
            .catch((error) => {
                if (!cancelled && error.name !== 'AbortError') {
                    console.error('Failed to fetch route', error);
                    setLoadingRoute(false);
                }
            });
        return () => {
            cancelled = true;
            controller.abort();
        };
    }, [data.source_location_id, data.target_location_id, shouldFetchRoute]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(game.transfers.store.url(), {
            onSuccess: () => {
                setIsDialogOpen(false);
                reset();
            },
        });
    };

    return (
        <>
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

                    <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                        <DialogTrigger asChild>
                            <Button className="gap-2 bg-amber-600 hover:bg-amber-700">
                                <Plus className="h-4 w-4" />
                                New Transfer
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-[500px]">
                            <form onSubmit={handleSubmit}>
                                <DialogHeader>
                                    <DialogTitle>
                                        Create Inter-Location Transfer
                                    </DialogTitle>
                                    <DialogDescription>
                                        Move inventory between locations. Routes
                                        are affected by active weather events.
                                    </DialogDescription>
                                </DialogHeader>
                                <div className="grid gap-4 py-4">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="source">
                                                Source
                                            </Label>
                                            <Select
                                                value={data.source_location_id}
                                                onValueChange={(val) =>
                                                    setData(
                                                        'source_location_id',
                                                        val,
                                                    )
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select source" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {locations.map((loc) => (
                                                        <SelectItem
                                                            key={loc.id}
                                                            value={loc.id}
                                                        >
                                                            {loc.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="target">
                                                Destination
                                            </Label>
                                            <Select
                                                value={data.target_location_id}
                                                onValueChange={(val) =>
                                                    setData(
                                                        'target_location_id',
                                                        val,
                                                    )
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select target" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {locations.map((loc) => (
                                                        <SelectItem
                                                            key={loc.id}
                                                            value={loc.id}
                                                        >
                                                            {loc.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>

                                    {/* Route Awareness UI */}
                                    {loadingRoute && (
                                        <div className="flex items-center gap-2 py-2 text-sm text-stone-500">
                                            <div className="h-4 w-4 animate-spin rounded-full border-2 border-stone-300 border-t-amber-600" />
                                            Calculating optimal route...
                                        </div>
                                    )}

                                    {routeInfo && !loadingRoute && (
                                        <div
                                            className={`space-y-3 rounded-lg border p-3 ${routeInfo.reachable ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/30' : 'border-rose-200 bg-rose-50 dark:border-rose-900 dark:bg-rose-950/30'}`}
                                        >
                                            <div className="flex items-start gap-2">
                                                {routeInfo.reachable ? (
                                                    <Truck className="mt-0.5 h-4 w-4 text-emerald-600" />
                                                ) : (
                                                    <AlertTriangle className="mt-0.5 h-4 w-4 text-rose-600" />
                                                )}
                                                <div className="flex-1">
                                                    <p
                                                        className={`text-sm font-bold ${routeInfo.reachable ? 'text-emerald-800 dark:text-emerald-400' : 'text-rose-800 dark:text-rose-400'}`}
                                                    >
                                                        {routeInfo.reachable
                                                            ? hasPremiumRoute &&
                                                              !showAlternative
                                                                ? 'Direct Route Blocked'
                                                                : 'Route Active'
                                                            : 'All Routes Blocked'}
                                                    </p>

                                                    {routeInfo.reachable && (
                                                        <div className="mt-1">
                                                            <div className="flex items-center justify-between">
                                                                <p className="text-xs text-stone-600 dark:text-stone-400">
                                                                    Estimated
                                                                    cost:{' '}
                                                                    <span className="font-bold text-stone-900 dark:text-white">
                                                                        $
                                                                        {routeInfo.total_cost !==
                                                                        undefined
                                                                            ? formatCurrency(
                                                                                  routeInfo.total_cost,
                                                                              )
                                                                            : '0.00'}
                                                                    </span>
                                                                </p>
                                                                {hasPremiumRoute && (
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="border-amber-500 bg-amber-100 text-[10px] text-amber-700 dark:bg-amber-900/30"
                                                                    >
                                                                        Premium
                                                                        Alternative
                                                                    </Badge>
                                                                )}
                                                            </div>

                                                            {routeInfo.path && (
                                                                <div className="mt-2 flex flex-wrap items-center gap-1">
                                                                    {routeInfo.path.map(
                                                                        (
                                                                            step,
                                                                            i,
                                                                        ) => (
                                                                            <span
                                                                                key={
                                                                                    i
                                                                                }
                                                                                className="flex items-center gap-1 text-[10px] font-medium text-stone-500"
                                                                            >
                                                                                <span
                                                                                    className={
                                                                                        step.is_premium
                                                                                            ? 'font-bold text-amber-600'
                                                                                            : ''
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        step.source
                                                                                    }
                                                                                </span>
                                                                                <ArrowRight
                                                                                    size={
                                                                                        10
                                                                                    }
                                                                                />
                                                                                {i ===
                                                                                    routeInfo
                                                                                        .path!
                                                                                        .length -
                                                                                        1 && (
                                                                                    <span
                                                                                        className={
                                                                                            step.is_premium
                                                                                                ? 'font-bold text-amber-600'
                                                                                                : ''
                                                                                        }
                                                                                    >
                                                                                        {
                                                                                            step.target
                                                                                        }
                                                                                    </span>
                                                                                )}
                                                                            </span>
                                                                        ),
                                                                    )}
                                                                </div>
                                                            )}
                                                        </div>
                                                    )}

                                                    {!routeInfo.reachable && (
                                                        <p className="text-xs text-stone-600 dark:text-stone-400">
                                                            {routeInfo.message ||
                                                                'No available routes due to severe disruptions or distance.'}
                                                        </p>
                                                    )}
                                                </div>
                                            </div>

                                            {/* Informational Blocking for Premium Routes */}
                                            {routeInfo.reachable &&
                                                hasPremiumRoute &&
                                                !showAlternative && (
                                                    <div className="mt-2 rounded border border-amber-200 bg-amber-50 p-2 dark:border-amber-900 dark:bg-amber-950/30">
                                                        <div className="flex items-center justify-between gap-4">
                                                            <div className="flex items-center gap-2 text-xs text-amber-800 dark:text-amber-400">
                                                                <Info
                                                                    size={14}
                                                                    className="shrink-0"
                                                                />
                                                                Primary path is
                                                                currently
                                                                unavailable. An
                                                                expensive
                                                                alternative
                                                                route is
                                                                required.
                                                            </div>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                className="h-7 border-amber-500 bg-white text-[10px] font-bold text-amber-600 hover:bg-amber-100 dark:bg-stone-900"
                                                                onClick={() =>
                                                                    setShowAlternative(
                                                                        true,
                                                                    )
                                                                }
                                                            >
                                                                Authorize
                                                                Premium
                                                            </Button>
                                                        </div>
                                                    </div>
                                                )}
                                        </div>
                                    )}

                                    <div className="space-y-2">
                                        <Label>Items</Label>
                                        {data.items.map((item, index) => (
                                            <div
                                                key={index}
                                                className="flex gap-2"
                                            >
                                                <Select
                                                    value={item.product_id}
                                                    onValueChange={(val) => {
                                                        const newItems = [
                                                            ...data.items,
                                                        ];
                                                        newItems[
                                                            index
                                                        ].product_id = val;
                                                        setData(
                                                            'items',
                                                            newItems,
                                                        );
                                                    }}
                                                >
                                                    <SelectTrigger className="flex-1">
                                                        <SelectValue placeholder="Select product" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {products.map((p) => (
                                                            <SelectItem
                                                                key={p.id}
                                                                value={p.id}
                                                            >
                                                                {p.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <Input
                                                    type="number"
                                                    className="w-20"
                                                    value={item.quantity}
                                                    onChange={(e) => {
                                                        const newItems = [
                                                            ...data.items,
                                                        ];
                                                        newItems[
                                                            index
                                                        ].quantity = parseInt(
                                                            e.target.value,
                                                        );
                                                        setData(
                                                            'items',
                                                            newItems,
                                                        );
                                                    }}
                                                />
                                            </div>
                                        ))}
                                    </div>
                                </div>
                                <DialogFooter>
                                    <Button
                                        type="submit"
                                        disabled={
                                            processing ||
                                            !routeInfo?.reachable ||
                                            (hasPremiumRoute &&
                                                !showAlternative)
                                        }
                                        className="bg-amber-600 hover:bg-amber-700"
                                    >
                                        {processing
                                            ? 'Creating...'
                                            : hasPremiumRoute &&
                                                !showAlternative
                                              ? 'Authorization Required'
                                              : 'Confirm Transfer'}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
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
                                {
                                    transfers.filter(
                                        (t) => t.status === 'in_transit',
                                    ).length
                                }
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
                                {
                                    transfers.filter(
                                        (t) => t.status === 'completed',
                                    ).length
                                }
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
                            <div className="text-2xl font-bold">
                                {suggestions.length}
                            </div>
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
                                                {getLocationName(
                                                    transfer.source_location_id,
                                                    transfer.source_location
                                                        ?.name,
                                                )}
                                            </span>
                                            <ArrowRight className="h-4 w-4 text-stone-400" />
                                            <span className="font-medium">
                                                {getLocationName(
                                                    transfer.target_location_id,
                                                    transfer.target_location
                                                        ?.name,
                                                )}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {getStatusBadge(transfer.status)}
                                    </TableCell>
                                    <TableCell>
                                        {transfer.delivery_day
                                            ? `Day ${transfer.delivery_day}`
                                            : '-'}
                                    </TableCell>
                                    <TableCell className="text-stone-500">
                                        {new Date(
                                            transfer.created_at,
                                        ).toLocaleDateString()}
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
        </>
    );
}

Transfers.layout = (page: ReactNode) => (
    <GameLayout breadcrumbs={breadcrumbs}>{page}</GameLayout>
);
