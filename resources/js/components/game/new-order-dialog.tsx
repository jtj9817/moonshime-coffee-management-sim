import { useForm } from '@inertiajs/react';
import { Plus, ShoppingCart, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

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
import { RouteModel } from '@/types';
import { RouteCapacityMeter } from './route-capacity-meter';
import { RoutePicker } from './route-picker';

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
    const [selectedRoute, setSelectedRoute] = useState<RouteModel | null>(null);

    // For adding a new item
    const [currentProductId, setCurrentProductId] = useState<string>('');
    const [currentQuantity, setCurrentQuantity] = useState<number>(100);

    const { data, setData, post, processing, reset, errors } = useForm({
        vendor_id: '',
        location_id: '',
        route_id: 0,
        items: [] as OrderItemForm[],
    });

    const vendorOptions = vendorProducts.map((vp) => vp.vendor);
    const selectedVendorData = vendorProducts.find((vp) => vp.vendor.id === data.vendor_id);

    const vendorLocations = useMemo(() => {
        return locations.filter(l => l.type === 'vendor' || l.type === 'warehouse');
    }, [locations]);

    const playerStores = useMemo(() => {
        return locations.filter(l => l.type === 'store' || l.name.includes('Central'));
    }, [locations]);

    const handleAddItem = () => {
        if (!currentProductId) return;

        const product = selectedVendorData?.products.find(p => p.id === currentProductId);
        if (!product) return;

        const newItems = [...data.items, {
            product_id: currentProductId,
            quantity: currentQuantity,
            unit_price: 2.5, // Placeholder price
        }];

        setData('items', newItems);
        setCurrentProductId('');
        setCurrentQuantity(100);
    };

    const removeItem = (index: number) => {
        setData('items', data.items.filter((_, i) => i !== index));
    };

    const totalQuantity = data.items.reduce((sum, item) => sum + item.quantity, 0);
    const isOverCapacity = selectedRoute ? totalQuantity > selectedRoute.capacity : false;

    // Derived values for summary
    const itemsSubtotal = data.items.reduce((sum, i) => sum + i.quantity * i.unit_price, 0);
    const shippingCost = selectedRoute?.cost ?? 0;
    const totalCost = itemsSubtotal + shippingCost;
    const excess = selectedRoute ? Math.max(0, totalQuantity - selectedRoute.capacity) : 0;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!data.vendor_id || !data.location_id || !selectedRoute || data.items.length === 0 || isOverCapacity) {
            return;
        }

        post('/game/orders', {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                reset();
                setSelectedRoute(null);
                setSelectedSourceId('');
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl max-h-[90vh] flex flex-col p-0 overflow-hidden">
                <DialogHeader className="p-6 pb-2">
                    <DialogTitle className="flex items-center gap-2">
                        <ShoppingCart className="h-5 w-5 text-amber-600" />
                        Create New Purchase Order
                    </DialogTitle>
                    <DialogDescription>
                        Select a vendor, items, and shipping route.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="flex-1 overflow-y-auto px-6 py-2 space-y-6">
                    <div className="grid grid-cols-2 gap-4">
                        {/* Vendor Selection */}
                        <div className="space-y-2">
                            <Label>Vendor</Label>
                            <Select value={data.vendor_id} onValueChange={(v) => setData('vendor_id', v)}>
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

                        {/* Target Store */}
                        <div className="space-y-2">
                            <Label>Destination Store</Label>
                            <Select value={data.location_id} onValueChange={(v) => setData('location_id', v)}>
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

                    {/* Logistics Source Selection */}
                    <div className="space-y-2">
                        <Label>Ship From (Vendor Hub)</Label>
                        <Select value={selectedSourceId} onValueChange={setSelectedSourceId}>
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

                    {/* Item Selection */}
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
                                        <SelectValue placeholder={data.vendor_id ? "Pick a product" : "Select vendor first"} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {selectedVendorData?.products.map((p) => (
                                            <SelectItem key={p.id} value={p.id}>
                                                {p.name} ({p.category})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="w-32 space-y-2">
                                <Label className="text-xs">Quantity</Label>
                                <Input
                                    type="number"
                                    value={currentQuantity}
                                    onChange={(e) => setCurrentQuantity(parseInt(e.target.value))}
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

                        {/* Items List */}
                        {data.items.length > 0 && (
                            <div className="mt-4 space-y-2">
                                {data.items.map((item, index) => {
                                    const product = selectedVendorData?.products.find(p => p.id === item.product_id);
                                    return (
                                        <div key={index} className="flex items-center justify-between rounded-md bg-white p-2 text-sm shadow-sm dark:bg-stone-800">
                                            <span className="font-medium">{product?.name}</span>
                                            <div className="flex items-center gap-4">
                                                <span className="text-stone-500 font-mono">{item.quantity} units</span>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-7 w-7 text-rose-500"
                                                    onClick={() => removeItem(index)}
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

                    {/* Route Selection */}
                    <RoutePicker
                        sourceId={selectedSourceId}
                        targetId={data.location_id}
                        selectedRouteId={selectedRoute?.id}
                        onSelect={(r) => {
                            setSelectedRoute(r);
                            setData('route_id', r.id);
                        }}
                    />

                    {/* Validation Errors & Summary */}
                    <div className="rounded-lg bg-stone-100 p-4 dark:bg-stone-800">
                        <div className="flex flex-col gap-2 border-b border-stone-200 pb-2 mb-2 dark:border-stone-700">
                            <div className="flex justify-between text-sm">
                                <span className="text-stone-500">Items Subtotal</span>
                                <span>${itemsSubtotal.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-stone-500">Shipping Mode</span>
                                <span className="capitalize">{selectedRoute?.transport_mode ?? '—'}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-stone-500">Shipping Cost</span>
                                <span>${shippingCost.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                            </div>
                        </div>

                        <div className="flex justify-between font-bold text-lg">
                            <span>Total Cost</span>
                            <span>${totalCost.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                        </div>

                        {/* Error Messages */}
                        {(errors.total || errors.route_id || errors.vendor_id || errors.items) && (
                            <div className="mt-3 rounded border border-rose-200 bg-rose-50 p-2 text-xs text-rose-600 dark:border-rose-900/30 dark:bg-rose-900/20">
                                {errors.total && <div className="font-bold">{errors.total}</div>}
                                {errors.route_id && <div>Route Error: {errors.route_id}</div>}
                                {errors.vendor_id && <div>Vendor Error: {errors.vendor_id}</div>}
                                {typeof errors.items === 'string' && <div>{errors.items}</div>}
                            </div>
                        )}
                    </div>

                    {/* Capacity Validation */}
                    {selectedRoute && (
                        <div className="space-y-1">
                            <RouteCapacityMeter
                                currentQuantity={totalQuantity}
                                capacity={selectedRoute.capacity}
                            />
                            {excess > 0 && (
                                <p className="text-xs text-rose-600 font-medium text-right">
                                    Reduce order by {excess.toLocaleString()} units
                                </p>
                            )}
                        </div>
                    )}
                </form>

                <DialogFooter className="p-6 pt-2 border-t border-stone-100 dark:border-stone-800">
                    <Button variant="ghost" onClick={() => onOpenChange(false)} disabled={processing}>
                        Cancel
                    </Button>
                    <Button
                        onClick={handleSubmit}
                        disabled={processing || data.items.length === 0 || !selectedRoute || totalQuantity > (selectedRoute?.capacity ?? Infinity)}
                        className={`bg-amber-600 hover:bg-amber-700 w-full sm:w-auto ${processing ? 'opacity-80' : ''
                            }`}
                    >
                        {processing ? 'Placing Order...' : `Confirm Order ($${totalCost.toLocaleString()})`}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

