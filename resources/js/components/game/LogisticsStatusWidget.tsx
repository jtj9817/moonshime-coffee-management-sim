import { Activity, Zap } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface LogisticsStatusWidgetProps {
    health: number;
    activeSpikesCount: number;
}

export function LogisticsStatusWidget({ health, activeSpikesCount }: LogisticsStatusWidgetProps) {
    const isHealthy = health >= 90;
    const isCritical = health < 70;

    let statusColor = 'text-emerald-500';
    let borderColor = 'border-emerald-200 dark:border-emerald-800';
    let bgColor = 'bg-emerald-500';

    if (isCritical) {
        statusColor = 'text-rose-500';
        borderColor = 'border-rose-500 dark:border-rose-900';
        bgColor = 'bg-rose-500';
    } else if (!isHealthy) {
        statusColor = 'text-amber-500';
        borderColor = 'border-amber-400 dark:border-amber-700';
        bgColor = 'bg-amber-500';
    }

    return (
        <Card className={`overflow-hidden border-2 transition-all ${borderColor}`}>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-bold uppercase tracking-wider text-stone-500 dark:text-stone-400">
                    Logistics Health
                </CardTitle>
                <Activity className={`h-4 w-4 ${statusColor}`} />
            </CardHeader>
            <CardContent>
                <div className="flex items-end justify-between">
                    <div className="text-3xl font-black text-stone-900 dark:text-white">
                        {Math.round(health)}%
                    </div>
                    <div className={`flex items-center gap-1 text-xs font-bold ${statusColor}`}>
                        {isHealthy ? 'OPTIMAL' : isCritical ? 'CRITICAL' : 'DEGRADED'}
                    </div>
                </div>

                <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-stone-100 dark:bg-stone-800">
                    <div
                        className={`h-full transition-all duration-1000 ${bgColor}`}
                        style={{ width: `${health}%` }}
                    />
                </div>

                <div className="mt-4 flex items-center justify-between border-t border-stone-100 pt-3 dark:border-stone-800">
                    <div className="flex items-center gap-2 text-xs font-medium text-stone-500 dark:text-stone-400">
                        <Zap className="h-3 w-3" />
                        Active Disruptions
                    </div>
                    <div className={`flex h-5 w-5 items-center justify-center rounded text-[10px] font-bold ${activeSpikesCount > 0 ? 'bg-rose-500 text-white animate-pulse' : 'bg-stone-200 text-stone-600 dark:bg-stone-700 dark:text-stone-400'}`}>
                        {activeSpikesCount}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
