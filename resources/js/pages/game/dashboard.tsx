import { Head, Link } from '@inertiajs/react';
import {
    AlertOctagon,
    AlertTriangle,
    ArrowRight,
    Building2,
    CheckCircle2,
    Coffee,
    MapPin,
    Package,
    Target,
    TrendingUp,
    Truck,
    Warehouse,
    Zap,
} from 'lucide-react';
import { type ReactNode } from 'react';

import { DailyReportCard } from '@/components/game/DailyReportCard';
import { LogisticsStatusWidget } from '@/components/game/LogisticsStatusWidget';
import ResetGameButton from '@/components/game/reset-game-button';
import { WelcomeBanner } from '@/components/game/welcome-banner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useGame } from '@/contexts/game-context';
import GameLayout from '@/layouts/game-layout';
import { formatCurrency } from '@/lib/formatCurrency';
import {
    AlertModel,
    DashboardKPI,
    LocationModel,
    QuestModel,
    type BreadcrumbItem,
} from '@/types/index';

interface DailySummaryData {
    units_sold: number;
    lost_sales: number;
    storage_fees: number;
    revenue: number;
}

interface DashboardProps {
    alerts: AlertModel[];
    kpis: DashboardKPI[];
    quests: QuestModel[];
    logistics_health: number;
    active_spikes_count: number;
    dailyReport: {
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
    } | null;
    dailySummary: {
        id: string;
        type: string;
        message: string;
        severity: string;
        created_day: number;
        data: DailySummaryData;
    } | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mission Control', href: '/game/dashboard' },
];

const LOCATION_CATEGORIES = [
    {
        types: ['store'],
        label: 'Coffee Shops',
        Icon: Coffee,
        iconBg: 'bg-amber-100 dark:bg-amber-900/30',
        iconColor: 'text-amber-600 dark:text-amber-400',
        countBadge:
            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    },
    {
        types: ['vendor'],
        label: 'Vendors',
        Icon: Truck,
        iconBg: 'bg-blue-100 dark:bg-blue-900/30',
        iconColor: 'text-blue-600 dark:text-blue-400',
        countBadge:
            'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    },
    {
        types: ['hub', 'warehouse'],
        label: 'Distribution Hubs',
        Icon: Building2,
        iconBg: 'bg-purple-100 dark:bg-purple-900/30',
        iconColor: 'text-purple-600 dark:text-purple-400',
        countBadge:
            'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
    },
];

const LOCATION_TYPE_ICONS: Record<string, typeof Coffee> = {
    store: Coffee,
    vendor: Truck,
    hub: Building2,
    warehouse: Warehouse,
};

function QuestCard({ quest }: { quest: QuestModel }) {
    const progress = Math.min(
        100,
        (quest.currentValue / quest.targetValue) * 100,
    );

    return (
        <div
            className={`rounded-xl border-2 p-4 transition-all ${
                quest.isCompleted
                    ? 'border-emerald-200 bg-emerald-50 opacity-80 dark:border-emerald-800 dark:bg-emerald-950'
                    : 'border-stone-200 bg-white hover:border-amber-400 dark:border-stone-700 dark:bg-stone-800'
            }`}
        >
            <div className="mb-2 flex items-start justify-between">
                <Badge variant="secondary" className="text-[10px] uppercase">
                    {quest.type.replace('_', ' ')}
                </Badge>
                <div className="flex items-center gap-1 text-xs font-bold text-amber-600">
                    {quest.reward.xp} XP
                    {quest.reward.cash && (
                        <span className="ml-1 text-emerald-600">
                            +${formatCurrency(quest.reward.cash)}
                        </span>
                    )}
                </div>
            </div>
            <h4
                className={`text-sm font-bold ${
                    quest.isCompleted
                        ? 'text-emerald-800 line-through dark:text-emerald-300'
                        : 'text-stone-900 dark:text-white'
                }`}
            >
                {quest.title}
            </h4>
            <p className="mt-1 mb-3 text-xs text-stone-500 dark:text-stone-400">
                {quest.description}
            </p>

            {quest.isCompleted ? (
                <div className="flex items-center gap-2 text-xs font-bold text-emerald-600">
                    <CheckCircle2 size={16} /> Completed
                </div>
            ) : (
                <div className="h-1.5 w-full overflow-hidden rounded-full bg-stone-100 dark:bg-stone-700">
                    <div
                        className="h-full bg-amber-500 transition-all"
                        style={{ width: `${progress}%` }}
                    />
                </div>
            )}
        </div>
    );
}

function LocationCard({
    location,
    alerts,
}: {
    location: LocationModel;
    alerts: AlertModel[];
}) {
    const locationAlerts = alerts.filter((a) => a.location_id === location.id);
    const critical = locationAlerts.filter(
        (a) => a.severity === 'critical',
    ).length;
    const warning = locationAlerts.filter(
        (a) => a.severity === 'warning',
    ).length;
    const TypeIcon = LOCATION_TYPE_ICONS[location.type] || MapPin;

    let statusColor = 'bg-emerald-500';
    let borderColor = 'border-emerald-200 dark:border-emerald-800';

    if (critical > 0) {
        statusColor = 'bg-rose-500';
        borderColor = 'border-rose-500 ring-2 ring-rose-500/20';
    } else if (warning > 0) {
        statusColor = 'bg-amber-500';
        borderColor = 'border-amber-400 dark:border-amber-600';
    }

    return (
        <Link
            href={`/game/inventory?location=${location.id}`}
            className={`group relative overflow-hidden rounded-xl border-2 bg-white p-5 shadow-lg transition-transform hover:-translate-y-1 dark:bg-stone-800 ${borderColor}`}
        >
            {critical > 0 && (
                <div className="absolute top-0 right-0 p-2">
                    <div className="h-3 w-3 animate-ping rounded-full bg-rose-500" />
                </div>
            )}

            <div className="mb-4 flex items-center gap-3">
                <div
                    className={`flex h-10 w-10 items-center justify-center rounded-lg text-white shadow-md ${statusColor}`}
                >
                    <TypeIcon size={20} />
                </div>
                <div>
                    <h3 className="font-bold text-stone-900 transition-colors group-hover:text-amber-600 dark:text-white">
                        {location.name}
                    </h3>
                    <p className="text-xs text-stone-500 dark:text-stone-400">
                        {location.address}
                    </p>
                </div>
            </div>

            <div className="space-y-2">
                {locationAlerts.slice(0, 2).map((alert) => (
                    <div
                        key={alert.id}
                        className="flex items-center gap-2 rounded border border-stone-100 bg-stone-50 p-2 text-xs dark:border-stone-700 dark:bg-stone-900"
                    >
                        {alert.severity === 'critical' ? (
                            <Package size={12} className="text-rose-500" />
                        ) : (
                            <AlertOctagon
                                size={12}
                                className="text-amber-500"
                            />
                        )}
                        <span className="flex-1 truncate font-medium text-stone-700 dark:text-stone-300">
                            {alert.message}
                        </span>
                    </div>
                ))}
                {locationAlerts.length === 0 && (
                    <div className="flex items-center gap-1 rounded border border-emerald-100 bg-emerald-50 p-2 text-xs font-bold text-emerald-600 dark:border-emerald-800 dark:bg-emerald-950">
                        <CheckCircle2 size={12} /> Systems Normal
                    </div>
                )}

                {/* Empty State Call to Action */}
                {critical > 0 &&
                    locationAlerts.some((a) =>
                        a.message.toLowerCase().includes('stock'),
                    ) && (
                        <div className="mt-2 text-center">
                            <span className="animate-pulse text-[10px] font-bold text-rose-600 dark:text-rose-400">
                                CRITICAL SHORTAGE - ORDER NOW
                            </span>
                        </div>
                    )}

                {locationAlerts.length > 2 && (
                    <div className="text-center text-[10px] font-bold text-stone-400">
                        +{locationAlerts.length - 2} more issues
                    </div>
                )}
            </div>
        </Link>
    );
}

export default function Dashboard({
    alerts,
    kpis,
    quests,
    logistics_health,
    active_spikes_count,
    dailyReport,
    dailySummary,
}: DashboardProps) {
    const { locations, activeSpikes, gameState } = useGame();

    return (
        <>
            <Head title="Mission Control" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Mission Control
                    </h1>
                    <ResetGameButton />
                </div>

                {/* Day 1 Welcome Banner - hidden after first order is placed */}
                {gameState.day === 1 && !gameState.has_placed_first_order && (
                    <WelcomeBanner />
                )}

                {/* Daily Report Card - shows after day 1 */}
                {dailyReport && <DailyReportCard report={dailyReport} />}

                {/* Daily Summary */}
                {dailySummary && dailySummary.data && (
                    <Card className="border-blue-200 bg-blue-50/50 dark:border-blue-800 dark:bg-blue-950/30">
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center gap-2 text-sm font-medium text-blue-700 dark:text-blue-300">
                                <Package className="h-4 w-4" />
                                Day {dailySummary.created_day} Summary
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <div>
                                    <p className="text-xs text-stone-500 dark:text-stone-400">
                                        Units Sold
                                    </p>
                                    <p className="text-lg font-bold text-stone-900 dark:text-white">
                                        {dailySummary.data.units_sold}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-stone-500 dark:text-stone-400">
                                        Revenue
                                    </p>
                                    <p className="text-lg font-bold text-emerald-600">
                                        $
                                        {formatCurrency(
                                            dailySummary.data.revenue,
                                        )}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-stone-500 dark:text-stone-400">
                                        Lost Sales
                                    </p>
                                    <p
                                        className={`text-lg font-bold ${dailySummary.data.lost_sales > 0 ? 'text-rose-600' : 'text-stone-900 dark:text-white'}`}
                                    >
                                        {dailySummary.data.lost_sales}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-stone-500 dark:text-stone-400">
                                        Storage Fees
                                    </p>
                                    <p className="text-lg font-bold text-amber-600">
                                        $
                                        {formatCurrency(
                                            dailySummary.data.storage_fees,
                                        )}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Active Spike Alert */}
                {activeSpikes.length > 0 && (
                    <div className="flex items-center justify-between rounded-xl border-2 border-rose-500 bg-rose-50 p-4 dark:border-rose-600 dark:bg-rose-950">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-rose-500 p-2">
                                <Zap className="h-5 w-5 text-white" />
                            </div>
                            <div>
                                <h3 className="font-bold text-rose-700 dark:text-rose-300">
                                    {activeSpikes.length === 1
                                        ? `Active Spike: ${activeSpikes[0].name}`
                                        : `${activeSpikes.length} Active Spikes`}
                                </h3>
                                <p className="text-sm text-rose-600 dark:text-rose-400">
                                    {activeSpikes.length === 1
                                        ? activeSpikes[0].description
                                        : 'Multiple disruptions require your attention'}
                                </p>
                            </div>
                        </div>
                        <Link href="/game/spike-history">
                            <Button
                                variant="outline"
                                className="border-rose-500 text-rose-600 hover:bg-rose-100"
                            >
                                {activeSpikes.length === 1
                                    ? 'View Details'
                                    : 'Open War Room'}
                                <ArrowRight className="ml-2 h-4 w-4" />
                            </Button>
                        </Link>
                    </div>
                )}

                {/* KPIs */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <LogisticsStatusWidget
                        health={logistics_health}
                        activeSpikesCount={active_spikes_count}
                    />

                    {kpis.map((kpi, index) => {
                        const isCurrency =
                            kpi.label === 'Inventory Value' &&
                            typeof kpi.value === 'number';

                        return (
                            <Card key={index}>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium text-stone-500 dark:text-stone-400">
                                        {kpi.label}
                                    </CardTitle>
                                    {kpi.trend === 'up' && (
                                        <TrendingUp className="h-4 w-4 text-emerald-500" />
                                    )}
                                    {kpi.trend === 'down' && (
                                        <AlertTriangle className="h-4 w-4 text-rose-500" />
                                    )}
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold text-stone-900 dark:text-white">
                                        {isCurrency
                                            ? `$${formatCurrency(kpi.value as number)}`
                                            : kpi.value}
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                {/* Main Grid */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Locations */}
                    <div className="lg:col-span-2">
                        <h2 className="mb-6 flex items-center gap-2 text-lg font-bold text-stone-900 dark:text-white">
                            <MapPin className="h-5 w-5 text-amber-600" />
                            Location Status
                        </h2>
                        <div className="space-y-8">
                            {LOCATION_CATEGORIES.map((category) => {
                                const categoryLocations = locations.filter(
                                    (l) => category.types.includes(l.type),
                                );
                                if (categoryLocations.length === 0) return null;
                                return (
                                    <div key={category.label}>
                                        <div className="mb-3 flex items-center gap-3">
                                            <div
                                                className={`flex h-7 w-7 items-center justify-center rounded-md ${category.iconBg}`}
                                            >
                                                <category.Icon
                                                    className={`h-3.5 w-3.5 ${category.iconColor}`}
                                                />
                                            </div>
                                            <span className="text-sm font-bold tracking-wider text-stone-500 uppercase dark:text-stone-400">
                                                {category.label}
                                            </span>
                                            <div className="h-px flex-1 bg-stone-200 dark:bg-stone-700" />
                                            <span
                                                className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${category.countBadge}`}
                                            >
                                                {categoryLocations.length}
                                            </span>
                                        </div>
                                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                            {categoryLocations.map(
                                                (location) => (
                                                    <LocationCard
                                                        key={location.id}
                                                        location={location}
                                                        alerts={alerts}
                                                    />
                                                ),
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Quests */}
                    <div>
                        <h2 className="mb-4 flex items-center gap-2 text-lg font-bold text-stone-900 dark:text-white">
                            <Target className="h-5 w-5 text-amber-600" />
                            Active Quests
                        </h2>
                        <div className="space-y-4">
                            {quests.map((quest) => (
                                <QuestCard key={quest.id} quest={quest} />
                            ))}
                            {quests.length === 0 && (
                                <div className="rounded-xl border-2 border-dashed border-stone-200 p-6 text-center text-stone-500 dark:border-stone-700 dark:text-stone-400">
                                    No active quests
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (page: ReactNode) => (
    <GameLayout breadcrumbs={breadcrumbs}>{page}</GameLayout>
);
