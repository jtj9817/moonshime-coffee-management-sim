import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    AlertOctagon,
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    DollarSign,
    MapPin,
    Package,
    Target,
    TrendingUp,
    Zap,
} from 'lucide-react';
import { type ReactNode } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { LogisticsStatusWidget } from '@/components/game/LogisticsStatusWidget';
import { WelcomeBanner } from '@/components/game/welcome-banner';
import { useGame } from '@/contexts/game-context';
import GameLayout from '@/layouts/game-layout';
import { AlertModel, DashboardKPI, QuestModel, type BreadcrumbItem } from '@/types/index';

interface DashboardProps {
    alerts: AlertModel[];
    kpis: DashboardKPI[];
    quests: QuestModel[];
    logistics_health: number;
    active_spikes_count: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mission Control', href: '/game/dashboard' },
];

function QuestCard({ quest }: { quest: QuestModel }) {
    const progress = Math.min(100, (quest.currentValue / quest.targetValue) * 100);

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
                        <span className="ml-1 text-emerald-600">+${quest.reward.cash}</span>
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
            <p className="mb-3 mt-1 text-xs text-stone-500 dark:text-stone-400">
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

function LocationCard({ location, alerts }: { location: { id: string; name: string; address: string }; alerts: AlertModel[] }) {
    const locationAlerts = alerts.filter((a) => a.location_id === location.id);
    const critical = locationAlerts.filter((a) => a.severity === 'critical').length;
    const warning = locationAlerts.filter((a) => a.severity === 'warning').length;

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
                <div className="absolute right-0 top-0 p-2">
                    <div className="h-3 w-3 animate-ping rounded-full bg-rose-500" />
                </div>
            )}

            <div className="mb-4 flex items-center gap-3">
                <div
                    className={`flex h-10 w-10 items-center justify-center rounded-lg font-bold text-white shadow-md ${statusColor}`}
                >
                    {location.name.substring(0, 1)}
                </div>
                <div>
                    <h3 className="font-bold text-stone-900 transition-colors group-hover:text-amber-600 dark:text-white">
                        {location.name}
                    </h3>
                    <p className="text-xs text-stone-500 dark:text-stone-400">{location.address}</p>
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
                            <AlertOctagon size={12} className="text-amber-500" />
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
                {critical > 0 && locationAlerts.some(a => a.message.toLowerCase().includes('stock')) && (
                    <div className="mt-2 text-center">
                        <span className="text-[10px] font-bold text-rose-600 dark:text-rose-400 animate-pulse">
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

export default function Dashboard({ alerts, kpis, quests, logistics_health, active_spikes_count }: DashboardProps) {
    const { locations, currentSpike, gameState } = useGame();

    return (
        <>
            <Head title="Mission Control" />

            <div className="flex flex-col gap-6 p-6">
                {/* Day 1 Welcome Banner */}
                {gameState.day === 1 && (
                    <WelcomeBanner />
                )}

                {/* Active Spike Alert */}
                {currentSpike && (
                    <div className="flex items-center justify-between rounded-xl border-2 border-rose-500 bg-rose-50 p-4 dark:border-rose-600 dark:bg-rose-950">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-rose-500 p-2">
                                <Zap className="h-5 w-5 text-white" />
                            </div>
                            <div>
                                <h3 className="font-bold text-rose-700 dark:text-rose-300">
                                    Active Spike: {currentSpike.name}
                                </h3>
                                <p className="text-sm text-rose-600 dark:text-rose-400">
                                    {currentSpike.description}
                                </p>
                            </div>
                        </div>
                        <Link href="/game/spike-history">
                            <Button variant="outline" className="border-rose-500 text-rose-600 hover:bg-rose-100">
                                View Details
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
                    
                    {kpis.map((kpi, index) => (
                        <Card key={index}>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium text-stone-500 dark:text-stone-400">
                                    {kpi.label}
                                </CardTitle>
                                {kpi.trend === 'up' && <TrendingUp className="h-4 w-4 text-emerald-500" />}
                                {kpi.trend === 'down' && <AlertTriangle className="h-4 w-4 text-rose-500" />}
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-stone-900 dark:text-white">
                                    {kpi.value}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Main Grid */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Locations */}
                    <div className="lg:col-span-2">
                        <h2 className="mb-4 flex items-center gap-2 text-lg font-bold text-stone-900 dark:text-white">
                            <MapPin className="h-5 w-5 text-amber-600" />
                            Location Status
                        </h2>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {locations.map((location) => (
                                <LocationCard key={location.id} location={location} alerts={alerts} />
                            ))}
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
