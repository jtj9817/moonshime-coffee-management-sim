import { useForm } from '@inertiajs/react';
import { Plus, ShoppingCart, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import ScenarioPlanner from '@/components/game/ScenarioPlanner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
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
import { useGame } from '@/contexts/game-context';
import { formatCurrency } from '@/lib/formatCurrency';

import { RouteCapacityMeter } from './route-capacity-meter';

interface NewOrderDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    vendorProducts: Array<{
        vendor: { id: string; name: string; reliability_score: number };
        products: Array<{ id: string; name: string; category: string }>;
    }>;
}

interface OrderItemForm {
    product_id: string;
    quantity: number;
    unit_price: number;
}

export function NewOrderDialog({
    open,
    onOpenChange,
    vendorProducts,
}: NewOrderDialogProps) {
    const { locations } = useGame();

    const [selectedSourceId, setSelectedSourceId] = useState<string>('');
    const [scheduleEnabled, setScheduleEnabled] = useState(false);

    const [calculatedPath, setCalculatedPath] = useState<{
        reachable: boolean;
        path: Array<{
            id: number;
            source: string;
            target: string;
            transport_mode: string;
            cost: number;
            transit_days: number;
            capacity: number;
        }>;
        total_cost: number;
        total_days: number;
        min_capacity: number;
    } | null>(null);

    const [loadingPath, setLoadingPath] = useState(false);

    const [currentProductId, setCurrentProductId] = useState<string>('');
    const [currentQuantity, setCurrentQuantity] = useState<number>(100);

    const { data, setData, post, processing, reset, errors } = useForm({
        vendor_id: '',
        location_id: '',
        source_location_id: '',
        route_id: 0,
        items: [] as OrderItemForm[],
        interval_days: 7,
        auto_submit: false,
    });

    const vendorOptions = (vendorProducts ?? []).map((vp) => vp.vendor);
    const selectedVendorData = (vendorProducts ?? []).find(
        (vp) => vp.vendor.id === data.vendor_id,
    );

    const vendorLocations = useMemo(() => {
        return (locations ?? []).filter(
            (l) => l.type === 'vendor' || l.type === 'warehouse',
        );
    }, [locations]);

    const playerStores = useMemo(() => {
        return (locations ?? []).filter(
            (l) => l.type === 'store' || l.name.includes('Central'),
        );
    }, [locations]);

    if ((!selectedSourceId || !data.location_id) && calculatedPath !== null) {
        setCalculatedPath(null);
    }

    const [prevFetchKey, setPrevFetchKey] = useState('');
    const fetchKey = `${selectedSourceId}|${data.location_id}`;
    if (selectedSourceId && data.location_id && fetchKey !== prevFetchKey) {
        setPrevFetchKey(fetchKey);
        setLoadingPath(true);
    }

    useEffect(() => {
        if (!selectedSourceId || !data.location_id) {
            return;
        }

        let cancelled = false;
        fetch(
            `/game/logistics/path?source_id=${selectedSourceId}&target_id=${data.location_id}`,
        )
            .then((res) => res.json())
            .then((result) => {
                if (cancelled) return;
                if (result.success && result.reachable) {
                    const path = result.path as Array<{
                        transit_days: number;
                        capacity: number;
                        id: number;
                        source: string;
                        target: string;
                        transport_mode: string;
                        cost: number;
                    }>;
                    const totalCost = result.total_cost;

                    const totalDays = path.reduce(
                        (sum: number, leg) => sum + (leg.transit_days || 0),
                        0,
                    );
                    const minCapacity = Math.min(
                        ...path.map((leg) => leg.capacity || 1000),
                    );

                    setCalculatedPath({
                        reachable: true,
                        path: path,
                        total_cost: totalCost,
                        total_days: totalDays,
                        min_capacity: minCapacity,
                    });
                } else {
                    setCalculatedPath(null);
                }
            })
            .catch(() => {
                if (!cancelled) setCalculatedPath(null);
            })
            .finally(() => {
                if (!cancelled) setLoadingPath(false);
            });
        return () => {
            cancelled = true;
        };
    }, [selectedSourceId, data.location_id]);

    useEffect(() => {
        setData('source_location_id', selectedSourceId);
    }, [selectedSourceId, setData]);

    const handleAddItem = () => {
        if (!currentProductId) return;

        const product = selectedVendorData?.products.find(
            (p) => p.id === currentProductId,
        );
        if (!product) return;

        const newItems = [
            ...data.items,
            {
                product_id: currentProductId,
                quantity: currentQuantity,
                unit_price: 250,
            },
        ];

        setData('items', newItems);
        setCurrentProductId('');
        setCurrentQuantity(100);
    };

    const removeItem = (index: number) => {
        setData(
            'items',
            data.items.filter((_, i) => i !== index),
        );
    };

    const totalQuantity = data.items.reduce(
        (sum, item) => sum + item.quantity,
        0,
    );
    const isOverCapacity = calculatedPath
        ? totalQuantity > calculatedPath.min_capacity
        : false;

    const itemsSubtotal = data.items.reduce(
        (sum, i) => sum + i.quantity * i.unit_price,
        0,
    );
    const shippingCost = calculatedPath?.total_cost ?? 0;
    const totalCost = itemsSubtotal + shippingCost;
    const excess = calculatedPath
        ? Math.max(0, totalQuantity - calculatedPath.min_capacity)
        : 0;

    const resetDraft = () => {
        reset();
        setCalculatedPath(null);
        setSelectedSourceId('');
        setScheduleEnabled(false);
        setData('interval_days', 7);
        setData('auto_submit', false);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (
            !data.vendor_id ||
            !data.location_id ||
            !selectedSourceId ||
            !calculatedPath ||
            data.items.length === 0 ||
            isOverCapacity
        ) {
            return;
        }

        if (scheduleEnabled && data.interval_days < 1) {
            return;
        }

        post(scheduleEnabled ? '/game/orders/scheduled' : '/game/orders', {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                resetDraft();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[90vh] max-w-2xl flex-col overflow-hidden p-0">
                <DialogHeader className="p-6 pb-2">
                    <DialogTitle className="flex items-center gap-2">
                        <ShoppingCart className="h-5 w-5 text-amber-600" />
                        Create New Purchase Order
                    </DialogTitle>
                    <DialogDescription>
                        Select a vendor, items, and shipping route.
                    </DialogDescription>
                </DialogHeader>

                <form
                    onSubmit={handleSubmit}
                    className="flex-1 space-y-6 overflow-y-auto px-6 py-2"
                >
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label>Vendor</Label>
                            <Select
                                value={data.vendor_id}
                                onValueChange={(v) => setData('vendor_id', v)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select vendor" />
                                </SelectTrigger>
                                <SelectContent>
                                    {vendorOptions.map((v) => (
                                        <SelectItem key={v.id} value={v.id}>
                                            {v.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>Destination Store</Label>
                            <Select
                                value={data.location_id}
                                onValueChange={(v) => setData('location_id', v)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select store" />
                                </SelectTrigger>
                                <SelectContent>
                                    {playerStores.map((s) => (
                                        <SelectItem key={s.id} value={s.id}>
                                            {s.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label>Ship From (Vendor Hub)</Label>
                        <Select
                            value={selectedSourceId}
                            onValueChange={setSelectedSourceId}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select departure point" />
                            </SelectTrigger>
                            <SelectContent>
                                {vendorLocations.map((l) => (
                                    <SelectItem key={l.id} value={l.id}>
                                        {l.name} ({l.type})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-4 rounded-lg border border-stone-200 bg-stone-50/50 p-4 dark:border-stone-700 dark:bg-stone-900/50">
                        <div className="flex items-end gap-3">
                            <div className="flex-1 space-y-2">
                                <Label className="text-xs">Product</Label>
                                <Select
                                    value={currentProductId}
                                    onValueChange={setCurrentProductId}
                                    disabled={!data.vendor_id}
                                >
                                    <SelectTrigger>
                                        <SelectValue
                                            placeholder={
                                                data.vendor_id
                                                    ? 'Pick a product'
                                                    : 'Select vendor first'
                                            }
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {selectedVendorData?.products.map(
                                            (p) => (
                                                <SelectItem
                                                    key={p.id}
                                                    value={p.id}
                                                >
                                                    {p.name} ({p.category})
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="w-32 space-y-2">
                                <Label className="text-xs">Quantity</Label>
                                <Input
                                    type="number"
                                    value={currentQuantity}
                                    onChange={(e) =>
                                        setCurrentQuantity(
                                            Number.parseInt(e.target.value, 10),
                                        )
                                    }
                                    min={1}
                                />
                            </div>
                            <Button
                                type="button"
                                size="icon"
                                variant="outline"
                                onClick={handleAddItem}
                                disabled={!currentProductId}
                            >
                                <Plus className="h-4 w-4" />
                            </Button>
                        </div>

                        {data.items.length > 0 && (
                            <div className="mt-4 space-y-2">
                                {data.items.map((item, index) => {
                                    const product =
                                        selectedVendorData?.products.find(
                                            (p) => p.id === item.product_id,
                                        );
                                    return (
                                        <div
                                            key={`${item.product_id}-${index}`}
                                            className="flex items-center justify-between rounded-md bg-white p-2 text-sm shadow-sm dark:bg-stone-800"
                                        >
                                            <span className="font-medium">
                                                {product?.name}
                                            </span>
                                            <div className="flex items-center gap-4">
                                                <span className="font-mono text-stone-500">
                                                    {item.quantity} units
                                                </span>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-7 w-7 text-rose-500"
                                                    onClick={() =>
                                                        removeItem(index)
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>

                    <div className="space-y-4 rounded-lg border border-indigo-200 bg-indigo-50/50 p-4 dark:border-indigo-900/30 dark:bg-indigo-900/20">
                        <Label className="text-indigo-900 dark:text-indigo-300">
                            Logistics Path
                        </Label>

                        {loadingPath ? (
                            <div className="animate-pulse text-sm text-indigo-500">
                                Finding best route...
                            </div>
                        ) : calculatedPath ? (
                            <div className="space-y-3">
                                <div className="flex items-center justify-between text-sm font-medium">
                                    <span className="text-indigo-700 dark:text-indigo-400">
                                        Total Transit:{' '}
                                        {calculatedPath.total_days} days
                                    </span>
                                    <span className="text-indigo-700 dark:text-indigo-400">
                                        Capacity:{' '}
                                        {calculatedPath.min_capacity.toLocaleString()}{' '}
                                        units
                                    </span>
                                </div>

                                <div className="space-y-1">
                                    {calculatedPath.path.map((leg, i) => (
                                        <div
                                            key={`${leg.id}-${i}`}
                                            className="flex items-center gap-2 text-xs text-indigo-600 dark:text-indigo-400"
                                        >
                                            <div className="flex h-5 w-5 items-center justify-center rounded-full bg-indigo-200 text-[10px] font-bold">
                                                {i + 1}
                                            </div>
                                            <span>
                                                {leg.source} → {leg.target} (
                                                {leg.transport_mode})
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ) : (
                            <div className="text-sm text-stone-500 italic">
                                {selectedSourceId && data.location_id
                                    ? 'No route available.'
                                    : 'Select departure and destination to view route.'}
                            </div>
                        )}
                    </div>

                    <ScenarioPlanner
                        compact
                        title="Order Mini-Calc"
                        initialValues={{
                            leadTimeDays: calculatedPath?.total_days ?? 3,
                            reorderPoint: Math.max(
                                10,
                                Math.round(totalQuantity / 2),
                            ),
                        }}
                    />

                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/40 dark:bg-amber-900/10">
                        <label className="flex items-center gap-2 text-sm font-medium text-stone-800 dark:text-stone-200">
                            <input
                                type="checkbox"
                                checked={scheduleEnabled}
                                onChange={(event) =>
                                    setScheduleEnabled(event.target.checked)
                                }
                            />
                            Schedule this order
                        </label>

                        {scheduleEnabled && (
                            <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div className="space-y-1">
                                    <Label className="text-xs">
                                        Repeat every (days)
                                    </Label>
                                    <Input
                                        type="number"
                                        min={1}
                                        value={data.interval_days}
                                        onChange={(event) =>
                                            setData(
                                                'interval_days',
                                                Number.parseInt(
                                                    event.target.value,
                                                    10,
                                                ) || 1,
                                            )
                                        }
                                    />
                                </div>
                                <label className="mt-6 flex items-center gap-2 text-sm text-stone-700 dark:text-stone-300">
                                    <input
                                        type="checkbox"
                                        checked={data.auto_submit}
                                        onChange={(event) =>
                                            setData(
                                                'auto_submit',
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    Auto-submit when valid
                                </label>
                            </div>
                        )}
                    </div>

                    <div className="rounded-lg bg-stone-100 p-4 dark:bg-stone-800">
                        <div className="mb-2 flex flex-col gap-2 border-b border-stone-200 pb-2 dark:border-stone-700">
                            <div className="flex justify-between text-sm">
                                <span className="text-stone-500">
                                    Items Subtotal
                                </span>
                                <span>${formatCurrency(itemsSubtotal)}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-stone-500">
                                    Shipping Cost
                                </span>
                                <span>${formatCurrency(shippingCost)}</span>
                            </div>
                        </div>

                        <div className="flex justify-between text-lg font-bold">
                            <span>Total Cost</span>
                            <span>${formatCurrency(totalCost)}</span>
                        </div>

                        {(errors.vendor_id || errors.items) && (
                            <div className="mt-3 rounded border border-rose-200 bg-rose-50 p-2 text-xs text-rose-600 dark:border-rose-900/30 dark:bg-rose-900/20">
                                {errors.vendor_id && (
                                    <div>Vendor Error: {errors.vendor_id}</div>
                                )}
                                {typeof errors.items === 'string' && (
                                    <div>{errors.items}</div>
                                )}
                            </div>
                        )}
                    </div>

                    {calculatedPath && (
                        <div className="space-y-1">
                            <RouteCapacityMeter
                                currentQuantity={totalQuantity}
                                capacity={calculatedPath.min_capacity}
                            />
                            {excess > 0 && (
                                <p className="text-right text-xs font-medium text-rose-600">
                                    Reduce order by {excess.toLocaleString()}{' '}
                                    units
                                </p>
                            )}
                        </div>
                    )}
                </form>

                <DialogFooter className="border-t border-stone-100 p-6 pt-2 dark:border-stone-800">
                    <Button
                        variant="ghost"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        Cancel
                    </Button>
                    <Button
                        onClick={handleSubmit}
                        disabled={
                            processing ||
                            data.items.length === 0 ||
                            !calculatedPath ||
                            isOverCapacity
                        }
                        className={`w-full bg-amber-600 hover:bg-amber-700 sm:w-auto ${processing ? 'opacity-80' : ''}`}
                    >
                        {processing
                            ? scheduleEnabled
                                ? 'Scheduling...'
                                : 'Placing Order...'
                            : scheduleEnabled
                              ? 'Save Schedule'
                              : `Confirm Order ($${formatCurrency(totalCost)})`}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
