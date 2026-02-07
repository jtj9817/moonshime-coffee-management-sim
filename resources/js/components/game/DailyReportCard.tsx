import { CalendarDays, FileText, Package, TrendingUp, Zap } from 'lucide-react';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatCurrency } from '@/lib/formatCurrency';

interface DailyReportData {
    id: number;
    user_id: number;
    day: number;
    summary_data: {
        orders_placed: number;
        spikes_started: number;
        spikes_ended: number;
        alerts_generated: number;
        transfers_completed: number;
    };
    metrics: {
        cash: number;
        xp: number;
    };
}

interface DailyReportCardProps {
    report: DailyReportData;
}

export function DailyReportCard({ report }: DailyReportCardProps) {
    const { day, summary_data, metrics } = report;

    const stats = [
        {
            label: 'Orders Placed',
            value: summary_data.orders_placed,
            icon: Package,
            color: 'text-blue-500',
        },
        {
            label: 'Spikes Started',
            value: summary_data.spikes_started,
            icon: Zap,
            color: 'text-rose-500',
        },
        {
            label: 'Spikes Ended',
            value: summary_data.spikes_ended,
            icon: Zap,
            color: 'text-emerald-500',
        },
        {
            label: 'Alerts',
            value: summary_data.alerts_generated,
            icon: FileText,
            color: 'text-amber-500',
        },
        {
            label: 'Transfers',
            value: summary_data.transfers_completed,
            icon: TrendingUp,
            color: 'text-purple-500',
        },
    ];

    return (
        <Card className="border-2 border-amber-200 bg-gradient-to-br from-amber-50 to-orange-50 dark:border-amber-800 dark:from-amber-950/50 dark:to-orange-950/50">
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-lg font-bold text-amber-700 dark:text-amber-300">
                    <CalendarDays className="h-5 w-5" />
                    Day {day} Report
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    {stats.map((stat) => (
                        <div
                            key={stat.label}
                            className="rounded-lg border border-stone-200 bg-white p-3 text-center dark:border-stone-700 dark:bg-stone-800"
                        >
                            <stat.icon className={`mx-auto mb-1 h-4 w-4 ${stat.color}`} />
                            <div className="text-xl font-bold text-stone-900 dark:text-white">
                                {stat.value}
                            </div>
                            <div className="text-[10px] font-medium text-stone-500 dark:text-stone-400">
                                {stat.label}
                            </div>
                        </div>
                    ))}
                </div>
                <div className="mt-3 flex items-center justify-between rounded-lg bg-stone-100 px-3 py-2 dark:bg-stone-800">
                    <span className="text-xs font-medium text-stone-600 dark:text-stone-300">
                        End of Day Balance
                    </span>
                    <div className="flex items-center gap-4">
                        <span className="text-sm font-bold text-emerald-600 dark:text-emerald-400">
                            ${formatCurrency(metrics.cash ?? 0)}
                        </span>
                        <span className="text-sm font-bold text-amber-600 dark:text-amber-400">
                            {metrics.xp ?? 0} XP
                        </span>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
