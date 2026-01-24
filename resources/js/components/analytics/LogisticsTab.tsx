import { AlertTriangle, Package, Warehouse } from 'lucide-react';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface StorageUtilizationItem {
    location_id: string;
    name: string;
    capacity: number;
    used: number;
    percentage: number;
}

interface SpikeImpact {
    min_inventory: number;
    avg_inventory: number;
}

interface SpikeImpactItem {
    id: string;
    type: string;
    name: string;
    start_day: number;
    end_day: number;
    product_name: string;
    location_name: string;
    impact: SpikeImpact | null;
}

interface LogisticsTabProps {
    storageUtilization: StorageUtilizationItem[];
    spikeImpactAnalysis: SpikeImpactItem[];
}

function getUtilizationColor(percentage: number): string {
    if (percentage >= 90) return 'bg-red-500';
    if (percentage >= 75) return 'bg-amber-500';
    if (percentage >= 50) return 'bg-emerald-500';
    return 'bg-blue-500';
}

function getUtilizationStatus(percentage: number): string {
    if (percentage >= 90) return 'Critical';
    if (percentage >= 75) return 'High';
    if (percentage >= 50) return 'Normal';
    return 'Low';
}

export function LogisticsTab({ storageUtilization, spikeImpactAnalysis }: LogisticsTabProps) {
    return (
        <div className="flex flex-col gap-6">
            {/* Storage Utilization */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Warehouse className="h-5 w-5" />
                        Storage Utilization
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="space-y-6">
                        {storageUtilization.map((loc) => (
                            <div key={loc.location_id} className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <div className="font-medium text-stone-900 dark:text-white">
                                        {loc.name}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span
                                            className={`rounded-full px-2 py-0.5 text-xs font-medium text-white ${getUtilizationColor(loc.percentage)}`}
                                        >
                                            {getUtilizationStatus(loc.percentage)}
                                        </span>
                                        <span className="text-sm text-stone-500">
                                            {loc.used} / {loc.capacity} units
                                        </span>
                                    </div>
                                </div>
                                <div className="h-4 w-full overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                                    <div
                                        className={`h-full transition-all ${getUtilizationColor(loc.percentage)}`}
                                        style={{ width: `${Math.min(loc.percentage, 100)}%` }}
                                    />
                                </div>
                                <div className="text-right text-sm text-stone-500">
                                    {loc.percentage}% utilized
                                </div>
                            </div>
                        ))}
                        {storageUtilization.length === 0 && (
                            <div className="py-8 text-center text-stone-500">
                                No storage data available.
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Spike Impact Analysis */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <AlertTriangle className="h-5 w-5" />
                        Spike Impact Analysis
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    {spikeImpactAnalysis.length > 0 ? (
                        <div className="space-y-4">
                            {spikeImpactAnalysis.map((spike) => (
                                <div
                                    key={spike.id}
                                    className="rounded-lg border border-stone-200 p-4 dark:border-stone-700"
                                >
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <h4 className="font-semibold text-stone-900 dark:text-white">
                                                    {spike.name}
                                                </h4>
                                                <span className="rounded-full bg-stone-100 px-2 py-0.5 text-xs text-stone-600 dark:bg-stone-800 dark:text-stone-400">
                                                    {spike.type}
                                                </span>
                                            </div>
                                            <p className="mt-1 text-sm text-stone-500">
                                                Day {spike.start_day} - Day {spike.end_day}
                                            </p>
                                        </div>
                                        <div className="text-right text-sm">
                                            <div className="text-stone-600 dark:text-stone-400">
                                                {spike.product_name}
                                            </div>
                                            <div className="text-stone-500">{spike.location_name}</div>
                                        </div>
                                    </div>
                                    {spike.impact ? (
                                        <div className="mt-3 flex gap-4 border-t border-stone-100 pt-3 dark:border-stone-800">
                                            <div className="flex items-center gap-2">
                                                <Package className="h-4 w-4 text-amber-500" />
                                                <span className="text-sm text-stone-600 dark:text-stone-400">
                                                    Min Inventory:{' '}
                                                    <span className="font-medium text-stone-900 dark:text-white">
                                                        {spike.impact.min_inventory}
                                                    </span>
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Package className="h-4 w-4 text-blue-500" />
                                                <span className="text-sm text-stone-600 dark:text-stone-400">
                                                    Avg Inventory:{' '}
                                                    <span className="font-medium text-stone-900 dark:text-white">
                                                        {spike.impact.avg_inventory}
                                                    </span>
                                                </span>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="mt-3 border-t border-stone-100 pt-3 text-sm text-stone-500 dark:border-stone-800">
                                            No impact data available for this spike.
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="py-8 text-center text-stone-500">
                            No spike events recorded yet. Spikes may occur as the game progresses.
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
