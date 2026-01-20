import { AlertTriangle, Clock, DollarSign, Truck } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/formatCurrency';
import { RouteModel } from '@/types';

interface RoutePickerProps {
    sourceId?: string;
    targetId?: string;
    selectedRouteId?: number;
    onSelect: (route: RouteModel) => void;
    className?: string;
}

export function RoutePicker({
    sourceId,
    targetId,
    selectedRouteId,
    onSelect,
    className = '',
}: RoutePickerProps) {
    const [routes, setRoutes] = useState<RouteModel[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!sourceId || !targetId) {
            setRoutes([]);
            return;
        }

        const fetchRoutes = async () => {
            setLoading(true);
            setError(null);
            try {
                const response = await fetch(
                    `/game/logistics/routes?source_id=${sourceId}&target_id=${targetId}`
                );
                if (!response.ok) throw new Error('Failed to fetch routes');
                const result = await response.json();
                setRoutes(result.data ?? []);
            } catch (err) {
                setError('Could not load shipping routes.');
                console.error(err);
            } finally {
                setLoading(false);
            }
        };

        fetchRoutes();
    }, [sourceId, targetId]);

    if (!sourceId || !targetId) {
        return (
            <div className="rounded-lg border border-dashed border-stone-200 p-4 text-center text-sm text-stone-500 dark:border-stone-700">
                Select a source and target to see shipping options.
            </div>
        );
    }

    if (loading) {
        return (
            <div className="flex animate-pulse flex-col gap-2">
                {[1, 2].map((i) => (
                    <div key={i} className="h-20 rounded-lg bg-stone-100 dark:bg-stone-800" />
                ))}
            </div>
        );
    }

    if (error) {
        return (
            <div className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-600 dark:border-rose-900/50 dark:bg-rose-900/20">
                {error}
            </div>
        );
    }

    return (
        <div className={`flex flex-col gap-3 ${className}`}>
            <Label className="text-sm font-medium">Select Shipping Route</Label>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                {routes.map((route) => {
                    const isSelected = selectedRouteId === route.id;
                    const isBlocked = !route.is_active;
                    const blockedReason = route.blocked_reason || 'Route Temporarily Unavailable';

                    return (
                        <Card
                            key={route.id}
                            className={`relative cursor-pointer overflow-hidden transition-all hover:border-amber-400 ${isSelected
                                ? 'ring-2 ring-amber-500 border-amber-500'
                                : 'border-stone-200 dark:border-stone-700'
                                } ${isBlocked ? 'opacity-60 grayscale pointer-events-none' : ''}`}
                            onClick={() => !isBlocked && onSelect(route)}
                        >
                            <CardContent className="p-3">
                                <div className="flex items-center justify-between mb-2">
                                    <div className="flex items-center gap-2">
                                        <Truck className={`h-4 w-4 ${route.transport_mode === 'air' ? 'text-blue-500' : 'text-stone-400'}`} />
                                        <span className="font-bold capitalize text-sm">
                                            {route.transport_mode}
                                        </span>
                                    </div>
                                    <div className="flex gap-1">
                                        {route.is_premium && (
                                            <Badge variant="secondary" className="text-[10px] h-4 bg-amber-100 text-amber-800 hover:bg-amber-100 dark:bg-amber-900/30 dark:text-amber-400">Premium</Badge>
                                        )}
                                        {route.weather_vulnerability && (
                                            <Badge variant="outline" className="text-[10px] h-4 border-blue-200 text-blue-600 dark:border-blue-900 dark:text-blue-400">Weather Risk</Badge>
                                        )}
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-y-1 gap-x-2 text-xs text-stone-500">
                                    <div className="flex items-center gap-1">
                                        <DollarSign className="h-3 w-3" />
                                        <span>Cost: ${formatCurrency(route.cost)}</span>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <Clock className="h-3 w-3" />
                                        <span>Transit: {route.transit_days} {route.transit_days === 1 ? 'day' : 'days'}</span>
                                    </div>
                                    <div className="flex items-center gap-1 col-span-2">
                                        <span className="font-semibold">Capacity:</span> {route.capacity.toLocaleString()} units
                                    </div>
                                </div>

                                {isBlocked && (
                                    <div className="absolute inset-0 flex flex-col items-center justify-center bg-white/70 dark:bg-stone-900/70 backdrop-blur-[1px] p-2 text-center">
                                        <AlertTriangle className="h-5 w-5 text-rose-500 mb-1" />
                                        <span className="text-[10px] font-bold text-rose-600 uppercase">Route Blocked</span>
                                        <span className="text-[10px] text-rose-600 font-medium leading-tight mt-1">{blockedReason}</span>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    );
                })}
            </div>
            {routes.length === 0 && (
                <div className="rounded-lg border border-dashed border-stone-200 p-6 text-center text-sm text-stone-500 dark:border-stone-700">
                    No active routes found between these locations.
                </div>
            )}
        </div>
    );
}
