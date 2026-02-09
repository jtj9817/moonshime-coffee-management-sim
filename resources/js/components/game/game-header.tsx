import {
    AlertTriangle,
    Bell,
    MapPin,
    Star,
    TrendingUp,
    Zap,
} from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useGame } from '@/contexts/game-context';

import { AdvanceDayButton } from './advance-day-button';
import { CashDisplay } from './cash-display';
import { DayCounter } from './day-counter';

export function GameHeader() {
    const {
        gameState,
        locations,
        alerts,
        currentSpike,
        currentLocationId,
        setCurrentLocationId,
    } = useGame();

    const currentLocation =
        currentLocationId === 'all'
            ? null
            : locations.find((l) => l.id === currentLocationId);

    const getReputationColor = (reputation: number) => {
        if (reputation >= 80) return 'bg-emerald-500';
        if (reputation >= 50) return 'bg-amber-500';
        return 'bg-rose-500';
    };

    return (
        <TooltipProvider>
            <div className="flex items-center justify-between border-b border-sidebar-border bg-sidebar/50 px-4 py-2 backdrop-blur-sm">
                {/* Left: Location Selector + Active Spike */}
                <div className="flex items-center gap-4">
                    {/* Location Selector */}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="outline"
                                size="sm"
                                className="gap-2"
                            >
                                <MapPin className="h-4 w-4" />
                                {currentLocation?.name ?? 'All Locations'}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start">
                            <DropdownMenuItem
                                onClick={() => setCurrentLocationId('all')}
                            >
                                All Locations
                            </DropdownMenuItem>
                            {locations.map((location) => (
                                <DropdownMenuItem
                                    key={location.id}
                                    onClick={() =>
                                        setCurrentLocationId(location.id)
                                    }
                                >
                                    {location.name}
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>

                    {/* Active Spike Indicator */}
                    {currentSpike && (
                        <Badge
                            variant="destructive"
                            className="animate-pulse gap-1"
                        >
                            <Zap className="h-3 w-3" />
                            {currentSpike.name}
                        </Badge>
                    )}
                </div>

                {/* Center: Game Stats */}
                <div className="flex items-center gap-6">
                    {/* Cash */}
                    <CashDisplay cash={gameState.cash} />

                    {/* XP & Level */}
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <div className="flex items-center gap-2">
                                <Star className="h-4 w-4 text-amber-500" />
                                <span className="font-medium text-stone-600 dark:text-stone-300">
                                    Lvl {gameState.level}
                                </span>
                                <div className="h-1.5 w-16 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                                    <div
                                        className="h-full bg-amber-500 transition-all"
                                        style={{
                                            width: `${(gameState.xp % 1000) / 10}%`,
                                        }}
                                    />
                                </div>
                            </div>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>
                                Experience: {gameState.xp.toLocaleString()} XP
                            </p>
                            <p className="text-xs text-stone-400">
                                {1000 - (gameState.xp % 1000)} XP to next level
                            </p>
                        </TooltipContent>
                    </Tooltip>

                    {/* Reputation */}
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <div className="flex items-center gap-2">
                                <TrendingUp className="h-4 w-4 text-stone-400" />
                                <div className="h-1.5 w-16 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                                    <div
                                        className={`h-full transition-all ${getReputationColor(gameState.reputation)}`}
                                        style={{
                                            width: `${gameState.reputation}%`,
                                        }}
                                    />
                                </div>
                                <span className="text-sm text-stone-500 dark:text-stone-400">
                                    {gameState.reputation}%
                                </span>
                            </div>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>Reputation Score: {gameState.reputation}%</p>
                            <p className="text-xs text-stone-400">
                                Based on vendor relationships and performance
                            </p>
                        </TooltipContent>
                    </Tooltip>

                    {/* Strikes */}
                    {gameState.strikes > 0 && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <div className="flex items-center gap-1">
                                    {[...Array(3)].map((_, i) => (
                                        <AlertTriangle
                                            key={i}
                                            className={`h-4 w-4 ${
                                                i < gameState.strikes
                                                    ? 'text-rose-500'
                                                    : 'text-stone-300 dark:text-stone-600'
                                            }`}
                                        />
                                    ))}
                                </div>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>{gameState.strikes} of 3 Strikes</p>
                                <p className="text-xs text-stone-400">
                                    Critical alerts that need attention
                                </p>
                            </TooltipContent>
                        </Tooltip>
                    )}
                </div>

                {/* Right: Day Counter + Actions */}
                <div className="flex items-center gap-4">
                    {/* Alerts Dropdown */}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="relative"
                            >
                                <Bell className="h-4 w-4" />
                                {alerts.length > 0 && (
                                    <span className="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-rose-500 text-[10px] font-bold text-white">
                                        {alerts.length}
                                    </span>
                                )}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-80">
                            <div className="flex items-center justify-between border-b px-3 py-2">
                                <span className="text-xs font-semibold text-stone-500 uppercase">
                                    Comms Log
                                </span>
                                {alerts.length > 0 && (
                                    <Badge
                                        variant="destructive"
                                        className="text-[10px]"
                                    >
                                        {alerts.length} new
                                    </Badge>
                                )}
                            </div>
                            {alerts.length === 0 ? (
                                <div className="p-4 text-center text-sm text-stone-500">
                                    No signals received.
                                </div>
                            ) : (
                                alerts.map((alert) => (
                                    <DropdownMenuItem
                                        key={alert.id}
                                        className="flex cursor-pointer items-start gap-3 p-3"
                                        onClick={() => {
                                            // Navigate based on alert type
                                            const destination = (() => {
                                                switch (alert.type) {
                                                    case 'order_placed':
                                                        return '/game/ordering';
                                                    case 'transfer_completed':
                                                        return '/game/transfers';
                                                    case 'spike_occurred':
                                                        return '/game/spike-history';
                                                    case 'isolation':
                                                        return alert.location_id
                                                            ? `/game/dashboard?location=${alert.location_id}`
                                                            : '/game/dashboard';
                                                    default:
                                                        return '/game/dashboard';
                                                }
                                            })();
                                            window.location.href = destination;
                                        }}
                                    >
                                        <div
                                            className={`mt-1 h-2 w-2 flex-shrink-0 rounded-full ${
                                                alert.severity === 'critical'
                                                    ? 'bg-rose-500'
                                                    : alert.severity ===
                                                        'warning'
                                                      ? 'bg-amber-500'
                                                      : 'bg-blue-500'
                                            }`}
                                        />
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">
                                                {alert.message}
                                            </p>
                                            <p className="text-xs text-stone-500">
                                                {new Date(
                                                    alert.created_at,
                                                ).toLocaleTimeString()}
                                            </p>
                                        </div>
                                    </DropdownMenuItem>
                                ))
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>

                    {/* Day Counter */}
                    <DayCounter day={gameState.day} />

                    {/* Advance Day Button */}
                    <AdvanceDayButton />
                </div>
            </div>
        </TooltipProvider>
    );
}
