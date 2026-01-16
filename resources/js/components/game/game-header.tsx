import { useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Bell,
    Calendar,
    DollarSign,
    MapPin,
    Play,
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

export function GameHeader() {
    const {
        gameState,
        locations,
        alerts,
        currentSpike,
        currentLocationId,
        setCurrentLocationId,
    } = useGame();

    const { post, processing } = useForm({});

    const handleAdvanceDay = () => {
        post('/game/advance-day', {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const currentLocation =
        currentLocationId === 'all'
            ? null
            : locations.find((l) => l.id === currentLocationId);

    const getCashColor = (cash: number) => {
        if (cash > 500000) return 'text-emerald-600 dark:text-emerald-400';
        if (cash > 100000) return 'text-amber-600 dark:text-amber-400';
        return 'text-rose-600 dark:text-rose-400';
    };

    const getReputationColor = (reputation: number) => {
        if (reputation >= 80) return 'bg-emerald-500';
        if (reputation >= 50) return 'bg-amber-500';
        return 'bg-rose-500';
    };

    const formatCash = (cash: number) => {
        if (cash >= 1000000) return `$${(cash / 1000000).toFixed(2)}M`;
        if (cash >= 1000) return `$${(cash / 1000).toFixed(1)}K`;
        return `$${cash.toFixed(2)}`;
    };

    return (
        <TooltipProvider>
            <div className="flex items-center justify-between border-b border-sidebar-border bg-sidebar/50 px-4 py-2 backdrop-blur-sm">
                {/* Left: Location Selector + Active Spike */}
                <div className="flex items-center gap-4">
                    {/* Location Selector */}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="sm" className="gap-2">
                                <MapPin className="h-4 w-4" />
                                {currentLocation?.name ?? 'All Locations'}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start">
                            <DropdownMenuItem onClick={() => setCurrentLocationId('all')}>
                                All Locations
                            </DropdownMenuItem>
                            {locations.map((location) => (
                                <DropdownMenuItem
                                    key={location.id}
                                    onClick={() => setCurrentLocationId(location.id)}
                                >
                                    {location.name}
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>

                    {/* Active Spike Indicator */}
                    {currentSpike && (
                        <Badge variant="destructive" className="animate-pulse gap-1">
                            <Zap className="h-3 w-3" />
                            {currentSpike.name}
                        </Badge>
                    )}
                </div>

                {/* Center: Game Stats */}
                <div className="flex items-center gap-6">
                    {/* Cash */}
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <div className="flex items-center gap-2">
                                <DollarSign className="h-4 w-4 text-stone-400" />
                                <span className={`font-mono font-bold ${getCashColor(gameState.cash)}`}>
                                    {formatCash(gameState.cash)}
                                </span>
                            </div>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>Available Cash</p>
                            <p className="text-xs text-stone-400">
                                ${gameState.cash.toLocaleString()}
                            </p>
                        </TooltipContent>
                    </Tooltip>

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
                            <p>Experience: {gameState.xp.toLocaleString()} XP</p>
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
                                        style={{ width: `${gameState.reputation}%` }}
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
                                <p>
                                    {gameState.strikes} of 3 Strikes
                                </p>
                                <p className="text-xs text-stone-400">
                                    Critical alerts that need attention
                                </p>
                            </TooltipContent>
                        </Tooltip>
                    )}
                </div>

                {/* Right: Day Counter + Actions */}
                <div className="flex items-center gap-4">
                    {/* Alerts */}
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button variant="ghost" size="sm" className="relative">
                                <Bell className="h-4 w-4" />
                                {alerts.length > 0 && (
                                    <span className="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-rose-500 text-[10px] font-bold text-white">
                                        {alerts.length}
                                    </span>
                                )}
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>{alerts.length} unread alerts</p>
                        </TooltipContent>
                    </Tooltip>

                    {/* Day Counter */}
                    <div className="flex items-center gap-2 rounded-lg border border-stone-200 bg-white px-3 py-1.5 dark:border-stone-700 dark:bg-stone-800">
                        <Calendar className="h-4 w-4 text-stone-400" />
                        <span className="font-mono font-bold text-stone-900 dark:text-white">
                            Day {gameState.day}
                        </span>
                    </div>

                    {/* Advance Day Button */}
                    <Button
                        onClick={handleAdvanceDay}
                        disabled={processing}
                        size="sm"
                        className="gap-2 bg-amber-600 text-white hover:bg-amber-700"
                    >
                        <Play className="h-4 w-4" />
                        {processing ? 'Processing...' : 'Next Day'}
                    </Button>
                </div>
            </div>
        </TooltipProvider>
    );
}
