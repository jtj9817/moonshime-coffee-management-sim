
import { router } from '@inertiajs/react';
import {
  LayoutDashboard, Package, ShoppingCart, BarChart3, Coffee, Bell,
  MapPin, Menu, X, Users, ArrowRightLeft, Activity, PieChart,
  Clock, DollarSign, AlertOctagon, Layers
} from 'lucide-react';
import React, { useState, useEffect } from 'react';
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
  "Global Bean prices stabilize after harvest...",
  "Local competitor 'Starbrew' facing strike action...",
  "Pumpkin Spice demand projected to surge +200% this weekend...",
  "Supply chain disruption in Brazil affecting darker roasts...",
  "Mayor announces 'Coffee Week' - expect high foot traffic..."
];

const Layout: React.FC<LayoutProps> = ({ children }) => {
  // Use game context (returns null if not in GameProvider)
  const game = useOptionalGame();
  const locations = game?.locations ?? [];
  const currentLocationId = game?.currentLocationId ?? 'all';
  const setCurrentLocationId = game?.setCurrentLocationId ?? (() => { });
  const alerts = game?.alerts ?? [];
  const markAlertRead = game?.markAlertRead ?? (() => { });
  const gameState = game?.gameState ?? { cash: 0, xp: 0, day: 1, level: 1, reputation: 0, strikes: 0 };
  const location = useLocation();
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [isNotifOpen, setIsNotifOpen] = useState(false);
  const [tickerIndex, setTickerIndex] = useState(0);

  // Floating Text State
  const [floatingTexts, setFloatingTexts] = useState<FloatingTextEvent[]>([]);

  // Ticker Logic
  useEffect(() => {
    const interval = setInterval(() => {
      setTickerIndex(prev => (prev + 1) % NEWS_TICKER.length);
    }, 8000);
    return () => clearInterval(interval);
  }, []);

  // Event Listener for Game Feedback
  useEffect(() => {
    const handleFeedback = (e: Event) => {
      const detail = (e as CustomEvent).detail as FloatingTextEvent;
      setFloatingTexts(prev => [...prev, detail]);

      // Remove after animation
      setTimeout(() => {
        setFloatingTexts(prev => prev.filter(ft => ft.id !== detail.id));
      }, 1500);
    };

    window.addEventListener('game-feedback', handleFeedback);
    return () => window.removeEventListener('game-feedback', handleFeedback);
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

  const unreadCount = alerts.filter(a => !a.is_read).length;

  const navItems = [
    { path: '/dashboard', label: 'Mission Control', icon: <LayoutDashboard size={20} /> },
    { path: '/strategy', label: 'Strategy Deck', icon: <Layers size={20} /> },
    { path: '/inventory', label: 'Pantry', icon: <Package size={20} /> },
    { path: '/ordering', label: 'Procurement', icon: <ShoppingCart size={20} /> },
    { path: '/transfers', label: 'Logistics', icon: <ArrowRightLeft size={20} /> },
    { path: '/vendors', label: 'Suppliers', icon: <Users size={20} /> },
    { path: '/analytics', label: 'Analytics', icon: <BarChart3 size={20} /> },
    { path: '/spike-history', label: 'War Room', icon: <Activity size={20} /> },
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
    <div className="flex h-screen bg-stone-900 text-stone-100 font-sans overflow-hidden">

      {/* Floating Text Overlay */}
      {floatingTexts.map(ft => (
        <div
          key={ft.id}
          className={`fixed pointer-events-none z-[100] text-2xl font-black uppercase tracking-wider animate-float-up text-shadow-lg`}
          style={{
            left: '50%',
            top: '40%',
            transform: 'translate(-50%, -50%)',
            color: ft.type === 'positive' ? '#10b981' : ft.type === 'negative' ? '#ef4444' : ft.type === 'xp' ? '#fbbf24' : '#fff'
          }}
        >
          {ft.text}
        </div>
      ))}

      {/* Mobile Backdrop */}
      {isMobileMenuOpen && (
        <div
          className="fixed inset-0 bg-black/80 z-40 lg:hidden backdrop-blur-sm transition-opacity"
          onClick={() => setIsMobileMenuOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside className={`fixed inset-y-0 left-0 z-50 w-64 bg-black border-r border-stone-800 transform transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static flex flex-col ${isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full'}`}>
        <div className="p-6 flex items-center gap-3 border-b border-stone-800 h-20">
          <div className="bg-gradient-to-br from-amber-600 to-amber-800 p-2 rounded-lg shadow-lg shadow-amber-900/40">
            <Coffee size={24} className="text-white" />
          </div>
          <div>
            <h1 className="text-white font-bold text-lg tracking-tight leading-none font-mono">MOONSHINE</h1>
            <p className="text-[10px] text-stone-500 uppercase tracking-[0.2em] mt-1">Sim 2.0</p>
          </div>
          <button onClick={() => setIsMobileMenuOpen(false)} className="lg:hidden ml-auto text-stone-500">
            <X size={20} />
          </button>
        </div>

        <nav className="flex-1 p-4 space-y-2 mt-2 overflow-y-auto">
          <div className="text-[10px] font-bold text-stone-600 uppercase tracking-widest px-4 mb-2">Operations</div>
          {navItems.map((item) => (
            <NavLink
              key={item.path}
              to={item.path + (location.search || '')}
              onClick={() => setIsMobileMenuOpen(false)}
              className={({ isActive }) => `
                w-full flex items-center gap-3 px-4 py-3 rounded-none border-l-2 transition-all duration-200 group relative
                ${isActive
                  ? 'border-amber-500 bg-stone-800/50 text-amber-500 font-bold'
                  : 'border-transparent text-stone-500 hover:bg-stone-900 hover:text-stone-300 hover:border-stone-700'}
              `}
            >
              {({ isActive }) => (
                <>
                  <span className={isActive ? 'text-amber-500 drop-shadow-md' : 'text-stone-600 group-hover:text-stone-400'}>
                    {item.icon}
                  </span>
                  <span className="tracking-wide">{item.label}</span>
                </>
              )}
            </NavLink>
          ))}
        </nav>

        <div className="p-4 border-t border-stone-800 bg-stone-950">
          <div className="flex items-center gap-3">
            <div className="relative">
              <div className="w-10 h-10 rounded-full border border-stone-700 overflow-hidden bg-stone-800">
                <img src={userAvatarIcon} className="w-full h-full object-cover opacity-80" alt="User Avatar" />
              </div>
              <div className="absolute -bottom-1 -right-1 bg-amber-600 text-black text-[10px] font-black px-1 rounded">
                LVL {gameState.level}
              </div>
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-bold text-white truncate">Alex Roaster</p>
              <div className="w-full bg-stone-800 h-1.5 mt-1 rounded-full overflow-hidden">
                <div className="bg-amber-500 h-full" style={{ width: `${(gameState.xp % 1000) / 10}%` }}></div>
              </div>
              <p className="text-[10px] text-stone-500 mt-0.5">{gameState.xp} XP</p>
            </div>
          </div>
        </div>
      </aside>

      {/* Main Content Area */}
      <div className="flex-1 flex flex-col min-w-0 bg-stone-50">

        {/* HUD Header */}
        <header className="bg-black border-b border-stone-800 sticky top-0 z-30 shadow-xl">

          {/* News Ticker */}
          <div className="bg-amber-500 text-black text-[10px] font-mono font-bold py-0.5 px-4 overflow-hidden whitespace-nowrap flex justify-center">
            <span className="animate-pulse mr-2">● LIVE FEED:</span> {NEWS_TICKER[tickerIndex]}
          </div>

          <div className="h-16 flex items-center justify-between px-4 lg:px-6">

            <div className="flex items-center gap-6">
              <button onClick={() => setIsMobileMenuOpen(true)} className="lg:hidden text-stone-400">
                <Menu size={24} />
              </button>

              {/* Resource Bank */}
              <div className="hidden md:flex items-center gap-6">
                <div className="flex flex-col">
                  <span className="text-[10px] text-stone-500 uppercase font-bold tracking-wider">Budget</span>
                  <span className={`text-xl font-mono font-bold flex items-center gap-1 ${gameState.cash < 100000 ? 'text-rose-500 animate-pulse' : 'text-emerald-400'}`}>
                    <DollarSign size={16} /> {formatCurrency(gameState.cash)}
                  </span>
                </div>
                <div className="w-px h-8 bg-stone-800"></div>
                <div className="flex flex-col w-24">
                  <span className="text-[10px] text-stone-500 uppercase font-bold tracking-wider flex justify-between">
                    Reputation <span className="text-amber-500">{gameState.reputation}%</span>
                  </span>
                  <div className="w-full bg-stone-800 h-2 mt-1 rounded-full overflow-hidden border border-stone-700">
                    <div className={`h-full ${gameState.reputation > 50 ? 'bg-amber-500' : 'bg-rose-500'}`} style={{ width: `${gameState.reputation}%` }}></div>
                  </div>
                </div>
                <div className="w-px h-8 bg-stone-800"></div>
                <div className="flex flex-col">
                  <span className="text-[10px] text-stone-500 uppercase font-bold tracking-wider">Strikes</span>
                  <div className="flex gap-1 mt-1">
                    {[...Array(3)].map((_, i) => (
                      <AlertOctagon key={i} size={14} className={i < gameState.strikes ? 'text-rose-600 fill-rose-900' : 'text-stone-800'} />
                    ))}
                  </div>
                </div>
              </div>
            </div>

            {/* Clock & Controls */}
            <div className="flex items-center gap-4">

              {/* Location Selector (HUD Style) */}
              <div className="flex items-center gap-2 bg-stone-900 border border-stone-700 rounded px-2 py-1">
                <MapPin size={14} className="text-stone-400" />
                <select
                  value={currentLocationId}
                  onChange={(e) => setCurrentLocationId(e.target.value)}
                  className="bg-transparent text-stone-200 text-xs font-bold outline-none uppercase tracking-wide cursor-pointer"
                >
                  <option value="all">Global View</option>
                  {locations.map(l => (
                    <option key={l.id} value={l.id}>{l.name}</option>
                  ))}
                </select>
              </div>

              <div className="hidden sm:flex items-center gap-2 px-3 py-1 bg-stone-900 rounded border border-stone-700 text-stone-300">
                <Clock size={14} className="text-amber-500" />
                <span className="font-mono text-sm font-bold">{formatGameTime()}</span>
              </div>

              {/* Notifications */}
              <div className="relative">
                <button
                  onClick={() => setIsNotifOpen(!isNotifOpen)}
                  className="p-2 text-stone-400 hover:text-white hover:bg-stone-800 rounded-full relative transition-colors"
                >
                  <Bell size={20} />
                  {unreadCount > 0 && (
                    <span className="absolute top-1 right-1 w-2.5 h-2.5 bg-rose-500 border-2 border-black rounded-full animate-bounce"></span>
                  )}
                </button>

                {isNotifOpen && (
                  <>
                    <div className="fixed inset-0 z-10" onClick={() => setIsNotifOpen(false)}></div>
                    <div className="absolute right-0 mt-3 w-80 bg-stone-900 text-stone-200 rounded-xl shadow-2xl border border-stone-700 z-20 overflow-hidden">
                      <div className="p-3 border-b border-stone-800 bg-black/40 flex justify-between items-center">
                        <h4 className="font-bold text-xs uppercase tracking-wider text-stone-400">Comms Log</h4>
                        {unreadCount > 0 && <span className="bg-rose-900/50 text-rose-400 text-[10px] px-2 py-0.5 rounded border border-rose-900">{unreadCount} new</span>}
                      </div>
                      <div className="max-h-80 overflow-y-auto">
                        {alerts.length === 0 ? (
                          <div className="p-6 text-center text-stone-600 text-xs">No signals received.</div>
                        ) : (
                          alerts.map(alert => (
                            <div
                              key={alert.id}
                              onClick={() => handleNotificationClick(alert)}
                              className={`p-4 border-b border-stone-800 hover:bg-stone-800/50 cursor-pointer transition-colors ${alert.is_read ? 'opacity-50' : 'bg-stone-800/30'}`}
                            >
                              <div className="flex gap-3">
                                <div className={`w-1.5 h-1.5 mt-1.5 rounded-full flex-shrink-0 ${alert.severity === 'critical'
                                  ? 'bg-rose-500 shadow-[0_0_8px_rgba(244,63,94,0.6)]'
                                  : alert.severity === 'warning'
                                    ? 'bg-amber-500'
                                    : 'bg-blue-500'
                                  }`}></div>
                                <div>
                                  <p className="text-xs font-bold text-stone-200 leading-snug">{alert.message}</p>
                                  <p className="text-[10px] text-stone-500 mt-1 font-mono">{new Date(alert.created_at).toLocaleTimeString()}</p>
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
        <main className="flex-1 overflow-y-auto p-4 lg:p-6 bg-stone-100/50 scroll-smooth relative">
          <div className="max-w-7xl mx-auto">
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
