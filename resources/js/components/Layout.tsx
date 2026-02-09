import { router } from '@inertiajs/react';
import {
    Activity,
    AlertOctagon,
    ArrowRightLeft,
    BarChart3,
    Bell,
    Clock,
    Coffee,
    DollarSign,
    Layers,
    LayoutDashboard,
    MapPin,
    Menu,
    Package,
    PieChart,
    ShoppingCart,
    Users,
    X,
} from 'lucide-react';
import React, { useEffect, useState } from 'react';
import { NavLink, useLocation } from 'react-router-dom';

import { useOptionalGame } from '@/contexts/game-context';
import { formatCurrency } from '@/lib/formatCurrency';
import { AlertModel } from '@/types/index';

import { FloatingTextEvent } from '../types';

const userAvatarIcon = '/assets/user-avatar.svg';

interface LayoutProps {
    children: React.ReactNode;
}

const NEWS_TICKER = [
    'Global Bean prices stabilize after harvest...',
    "Local competitor 'Starbrew' facing strike action...",
    'Pumpkin Spice demand projected to surge +200% this weekend...',
    'Supply chain disruption in Brazil affecting darker roasts...',
    "Mayor announces 'Coffee Week' - expect high foot traffic...",
];

const Layout: React.FC<LayoutProps> = ({ children }) => {
    // Use game context (returns null if not in GameProvider)
    const game = useOptionalGame();
    const locations = game?.locations ?? [];
    const currentLocationId = game?.currentLocationId ?? 'all';
    const setCurrentLocationId = game?.setCurrentLocationId ?? (() => {});
    const alerts = game?.alerts ?? [];
    const markAlertRead = game?.markAlertRead ?? (() => {});
    const gameState = game?.gameState ?? {
        cash: 0,
        xp: 0,
        day: 1,
        level: 1,
        reputation: 0,
        strikes: 0,
    };
    const location = useLocation();
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
    const [isNotifOpen, setIsNotifOpen] = useState(false);
    const [tickerIndex, setTickerIndex] = useState(0);

    // Floating Text State
    const [floatingTexts, setFloatingTexts] = useState<FloatingTextEvent[]>([]);

    // Ticker Logic
    useEffect(() => {
        const interval = setInterval(() => {
            setTickerIndex((prev) => (prev + 1) % NEWS_TICKER.length);
        }, 8000);
        return () => clearInterval(interval);
    }, []);

    // Event Listener for Game Feedback
    useEffect(() => {
        const handleFeedback = (e: Event) => {
            const detail = (e as CustomEvent).detail as FloatingTextEvent;
            setFloatingTexts((prev) => [...prev, detail]);

            // Remove after animation
            setTimeout(() => {
                setFloatingTexts((prev) =>
                    prev.filter((ft) => ft.id !== detail.id),
                );
            }, 1500);
        };

        window.addEventListener('game-feedback', handleFeedback);
        return () =>
            window.removeEventListener('game-feedback', handleFeedback);
    }, []);

    // Navigation mapping for alert types
    const getAlertDestination = (alert: AlertModel): string => {
        switch (alert.type) {
            case 'order_placed':
                return '/game/ordering';
            case 'transfer_completed':
                return '/game/transfers';
            case 'spike_occurred':
                return '/game/war-room';
            case 'isolation':
                return alert.location_id
                    ? `/game/dashboard?location=${alert.location_id}`
                    : '/game/dashboard';
            default:
                return '/game/dashboard';
        }
    };

    // Handle notification click: mark read + navigate
    const handleNotificationClick = (alert: AlertModel) => {
        markAlertRead(alert.id);
        setIsNotifOpen(false);
        router.visit(getAlertDestination(alert));
    };

    const unreadCount = alerts.filter((a) => !a.is_read).length;

    const navItems = [
        {
            path: '/dashboard',
            label: 'Mission Control',
            icon: <LayoutDashboard size={20} />,
        },
        {
            path: '/strategy',
            label: 'Strategy Deck',
            icon: <Layers size={20} />,
        },
        { path: '/inventory', label: 'Pantry', icon: <Package size={20} /> },
        {
            path: '/ordering',
            label: 'Procurement',
            icon: <ShoppingCart size={20} />,
        },
        {
            path: '/transfers',
            label: 'Logistics',
            icon: <ArrowRightLeft size={20} />,
        },
        { path: '/vendors', label: 'Suppliers', icon: <Users size={20} /> },
        {
            path: '/analytics',
            label: 'Analytics',
            icon: <BarChart3 size={20} />,
        },
        {
            path: '/spike-history',
            label: 'War Room',
            icon: <Activity size={20} />,
        },
        { path: '/reports', label: 'Wastage', icon: <PieChart size={20} /> },
    ];

    const formatGameTime = () => {
        const { day, hour, minute } = gameState.time;
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const h = hour % 12 || 12;
        const m = minute.toString().padStart(2, '0');
        return `Day ${day} • ${h}:${m} ${ampm}`;
    };

    return (
        <div className="flex h-screen overflow-hidden bg-stone-900 font-sans text-stone-100">
            {/* Floating Text Overlay */}
            {floatingTexts.map((ft) => (
                <div
                    key={ft.id}
                    className={`animate-float-up pointer-events-none fixed z-[100] text-2xl font-black tracking-wider uppercase text-shadow-lg`}
                    style={{
                        left: '50%',
                        top: '40%',
                        transform: 'translate(-50%, -50%)',
                        color:
                            ft.type === 'positive'
                                ? '#10b981'
                                : ft.type === 'negative'
                                  ? '#ef4444'
                                  : ft.type === 'xp'
                                    ? '#fbbf24'
                                    : '#fff',
                    }}
                >
                    {ft.text}
                </div>
            ))}

            {/* Mobile Backdrop */}
            {isMobileMenuOpen && (
                <div
                    className="fixed inset-0 z-40 bg-black/80 backdrop-blur-sm transition-opacity lg:hidden"
                    onClick={() => setIsMobileMenuOpen(false)}
                />
            )}

            {/* Sidebar */}
            <aside
                className={`fixed inset-y-0 left-0 z-50 flex w-64 transform flex-col border-r border-stone-800 bg-black transition-transform duration-300 ease-in-out lg:static lg:translate-x-0 ${isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full'}`}
            >
                <div className="flex h-20 items-center gap-3 border-b border-stone-800 p-6">
                    <div className="rounded-lg bg-gradient-to-br from-amber-600 to-amber-800 p-2 shadow-lg shadow-amber-900/40">
                        <Coffee size={24} className="text-white" />
                    </div>
                    <div>
                        <h1 className="font-mono text-lg leading-none font-bold tracking-tight text-white">
                            MOONSHINE
                        </h1>
                        <p className="mt-1 text-[10px] tracking-[0.2em] text-stone-500 uppercase">
                            Sim 2.0
                        </p>
                    </div>
                    <button
                        onClick={() => setIsMobileMenuOpen(false)}
                        className="ml-auto text-stone-500 lg:hidden"
                    >
                        <X size={20} />
                    </button>
                </div>

                <nav className="mt-2 flex-1 space-y-2 overflow-y-auto p-4">
                    <div className="mb-2 px-4 text-[10px] font-bold tracking-widest text-stone-600 uppercase">
                        Operations
                    </div>
                    {navItems.map((item) => (
                        <NavLink
                            key={item.path}
                            to={item.path + (location.search || '')}
                            onClick={() => setIsMobileMenuOpen(false)}
                            className={({ isActive }) =>
                                `group relative flex w-full items-center gap-3 rounded-none border-l-2 px-4 py-3 transition-all duration-200 ${
                                    isActive
                                        ? 'border-amber-500 bg-stone-800/50 font-bold text-amber-500'
                                        : 'border-transparent text-stone-500 hover:border-stone-700 hover:bg-stone-900 hover:text-stone-300'
                                } `
                            }
                        >
                            {({ isActive }) => (
                                <>
                                    <span
                                        className={
                                            isActive
                                                ? 'text-amber-500 drop-shadow-md'
                                                : 'text-stone-600 group-hover:text-stone-400'
                                        }
                                    >
                                        {item.icon}
                                    </span>
                                    <span className="tracking-wide">
                                        {item.label}
                                    </span>
                                </>
                            )}
                        </NavLink>
                    ))}
                </nav>

                <div className="border-t border-stone-800 bg-stone-950 p-4">
                    <div className="flex items-center gap-3">
                        <div className="relative">
                            <div className="h-10 w-10 overflow-hidden rounded-full border border-stone-700 bg-stone-800">
                                <img
                                    src={userAvatarIcon}
                                    className="h-full w-full object-cover opacity-80"
                                    alt="User Avatar"
                                />
                            </div>
                            <div className="absolute -right-1 -bottom-1 rounded bg-amber-600 px-1 text-[10px] font-black text-black">
                                LVL {gameState.level}
                            </div>
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-bold text-white">
                                Alex Roaster
                            </p>
                            <div className="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-stone-800">
                                <div
                                    className="h-full bg-amber-500"
                                    style={{
                                        width: `${(gameState.xp % 1000) / 10}%`,
                                    }}
                                ></div>
                            </div>
                            <p className="mt-0.5 text-[10px] text-stone-500">
                                {gameState.xp} XP
                            </p>
                        </div>
                    </div>
                </div>
            </aside>

            {/* Main Content Area */}
            <div className="flex min-w-0 flex-1 flex-col bg-stone-50">
                {/* HUD Header */}
                <header className="sticky top-0 z-30 border-b border-stone-800 bg-black shadow-xl">
                    {/* News Ticker */}
                    <div className="flex justify-center overflow-hidden bg-amber-500 px-4 py-0.5 font-mono text-[10px] font-bold whitespace-nowrap text-black">
                        <span className="mr-2 animate-pulse">● LIVE FEED:</span>{' '}
                        {NEWS_TICKER[tickerIndex]}
                    </div>

                    <div className="flex h-16 items-center justify-between px-4 lg:px-6">
                        <div className="flex items-center gap-6">
                            <button
                                onClick={() => setIsMobileMenuOpen(true)}
                                className="text-stone-400 lg:hidden"
                            >
                                <Menu size={24} />
                            </button>

                            {/* Resource Bank */}
                            <div className="hidden items-center gap-6 md:flex">
                                <div className="flex flex-col">
                                    <span className="text-[10px] font-bold tracking-wider text-stone-500 uppercase">
                                        Budget
                                    </span>
                                    <span
                                        className={`flex items-center gap-1 font-mono text-xl font-bold ${gameState.cash < 100000 ? 'animate-pulse text-rose-500' : 'text-emerald-400'}`}
                                    >
                                        <DollarSign size={16} />{' '}
                                        {formatCurrency(gameState.cash)}
                                    </span>
                                </div>
                                <div className="h-8 w-px bg-stone-800"></div>
                                <div className="flex w-24 flex-col">
                                    <span className="flex justify-between text-[10px] font-bold tracking-wider text-stone-500 uppercase">
                                        Reputation{' '}
                                        <span className="text-amber-500">
                                            {gameState.reputation}%
                                        </span>
                                    </span>
                                    <div className="mt-1 h-2 w-full overflow-hidden rounded-full border border-stone-700 bg-stone-800">
                                        <div
                                            className={`h-full ${gameState.reputation > 50 ? 'bg-amber-500' : 'bg-rose-500'}`}
                                            style={{
                                                width: `${gameState.reputation}%`,
                                            }}
                                        ></div>
                                    </div>
                                </div>
                                <div className="h-8 w-px bg-stone-800"></div>
                                <div className="flex flex-col">
                                    <span className="text-[10px] font-bold tracking-wider text-stone-500 uppercase">
                                        Strikes
                                    </span>
                                    <div className="mt-1 flex gap-1">
                                        {[...Array(3)].map((_, i) => (
                                            <AlertOctagon
                                                key={i}
                                                size={14}
                                                className={
                                                    i < gameState.strikes
                                                        ? 'fill-rose-900 text-rose-600'
                                                        : 'text-stone-800'
                                                }
                                            />
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Clock & Controls */}
                        <div className="flex items-center gap-4">
                            {/* Location Selector (HUD Style) */}
                            <div className="flex items-center gap-2 rounded border border-stone-700 bg-stone-900 px-2 py-1">
                                <MapPin size={14} className="text-stone-400" />
                                <select
                                    value={currentLocationId}
                                    onChange={(e) =>
                                        setCurrentLocationId(e.target.value)
                                    }
                                    className="cursor-pointer bg-transparent text-xs font-bold tracking-wide text-stone-200 uppercase outline-none"
                                >
                                    <option value="all">Global View</option>
                                    {locations.map((l) => (
                                        <option key={l.id} value={l.id}>
                                            {l.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="hidden items-center gap-2 rounded border border-stone-700 bg-stone-900 px-3 py-1 text-stone-300 sm:flex">
                                <Clock size={14} className="text-amber-500" />
                                <span className="font-mono text-sm font-bold">
                                    {formatGameTime()}
                                </span>
                            </div>

                            {/* Notifications */}
                            <div className="relative">
                                <button
                                    onClick={() => setIsNotifOpen(!isNotifOpen)}
                                    className="relative rounded-full p-2 text-stone-400 transition-colors hover:bg-stone-800 hover:text-white"
                                >
                                    <Bell size={20} />
                                    {unreadCount > 0 && (
                                        <span className="absolute top-1 right-1 h-2.5 w-2.5 animate-bounce rounded-full border-2 border-black bg-rose-500"></span>
                                    )}
                                </button>

                                {isNotifOpen && (
                                    <>
                                        <div
                                            className="fixed inset-0 z-10"
                                            onClick={() =>
                                                setIsNotifOpen(false)
                                            }
                                        ></div>
                                        <div className="absolute right-0 z-20 mt-3 w-80 overflow-hidden rounded-xl border border-stone-700 bg-stone-900 text-stone-200 shadow-2xl">
                                            <div className="flex items-center justify-between border-b border-stone-800 bg-black/40 p-3">
                                                <h4 className="text-xs font-bold tracking-wider text-stone-400 uppercase">
                                                    Comms Log
                                                </h4>
                                                {unreadCount > 0 && (
                                                    <span className="rounded border border-rose-900 bg-rose-900/50 px-2 py-0.5 text-[10px] text-rose-400">
                                                        {unreadCount} new
                                                    </span>
                                                )}
                                            </div>
                                            <div className="max-h-80 overflow-y-auto">
                                                {alerts.length === 0 ? (
                                                    <div className="p-6 text-center text-xs text-stone-600">
                                                        No signals received.
                                                    </div>
                                                ) : (
                                                    alerts.map((alert) => (
                                                        <div
                                                            key={alert.id}
                                                            onClick={() =>
                                                                handleNotificationClick(
                                                                    alert,
                                                                )
                                                            }
                                                            className={`cursor-pointer border-b border-stone-800 p-4 transition-colors hover:bg-stone-800/50 ${alert.is_read ? 'opacity-50' : 'bg-stone-800/30'}`}
                                                        >
                                                            <div className="flex gap-3">
                                                                <div
                                                                    className={`mt-1.5 h-1.5 w-1.5 flex-shrink-0 rounded-full ${
                                                                        alert.severity ===
                                                                        'critical'
                                                                            ? 'bg-rose-500 shadow-[0_0_8px_rgba(244,63,94,0.6)]'
                                                                            : alert.severity ===
                                                                                'warning'
                                                                              ? 'bg-amber-500'
                                                                              : 'bg-blue-500'
                                                                    }`}
                                                                ></div>
                                                                <div>
                                                                    <p className="text-xs leading-snug font-bold text-stone-200">
                                                                        {
                                                                            alert.message
                                                                        }
                                                                    </p>
                                                                    <p className="mt-1 font-mono text-[10px] text-stone-500">
                                                                        {new Date(
                                                                            alert.created_at,
                                                                        ).toLocaleTimeString()}
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    ))
                                                )}
                                            </div>
                                        </div>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                </header>

                {/* Dynamic Page Content */}
                <main className="relative flex-1 overflow-y-auto scroll-smooth bg-stone-100/50 p-4 lg:p-6">
                    <div className="mx-auto max-w-7xl">
                        <style>{`
                @keyframes float-up {
                    0% { opacity: 0; transform: translate(-50%, -20%) scale(0.8); }
                    20% { opacity: 1; transform: translate(-50%, -50%) scale(1.2); }
                    80% { opacity: 1; transform: translate(-50%, -80%) scale(1); }
                    100% { opacity: 0; transform: translate(-50%, -100%) scale(0.9); }
                }
                .animate-float-up {
                    animation: float-up 1.5s ease-out forwards;
                }
                .text-shadow-lg {
                    text-shadow: 0 4px 12px rgba(0,0,0,0.5);
                }
             `}</style>
                        {children}
                    </div>
                </main>
            </div>
        </div>
    );
};

export default Layout;
