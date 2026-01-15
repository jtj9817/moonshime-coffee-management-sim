

import React, { useEffect, useState } from 'react';
import { 
  AlertTriangle, ArrowRight, Activity, DollarSign, Trash2, 
  AlertOctagon, Zap, TrendingUp, CheckCircle2, MapPin, 
  Package, Truck, MoreHorizontal, Target
} from 'lucide-react';
import { useApp } from '../App';
import { Link } from 'react-router-dom';
import { generateAlerts } from '../services/cockpitService';
import { Alert, Quest } from '../types';
import SpikeMonitor from './SpikeMonitor';

// --- SUB-COMPONENTS ---

const QuestCard: React.FC<{ quest: Quest; onClaim: (id: string) => void }> = ({ quest, onClaim }) => (
    <div className={`p-4 rounded-xl border-2 transition-all ${quest.isCompleted ? 'bg-emerald-50 border-emerald-200 opacity-80' : 'bg-white border-stone-200 hover:border-amber-400'}`}>
        <div className="flex justify-between items-start mb-2">
            <span className={`text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded ${quest.isCompleted ? 'bg-emerald-200 text-emerald-800' : 'bg-stone-100 text-stone-500'}`}>
                {quest.type.replace('_', ' ')}
            </span>
            <div className="flex items-center gap-1 text-xs font-bold text-amber-600">
                {quest.reward.xp} XP
                {quest.reward.cash && <span className="text-emerald-600 ml-1">+${quest.reward.cash}</span>}
            </div>
        </div>
        <h4 className={`font-bold text-sm ${quest.isCompleted ? 'text-emerald-800 line-through' : 'text-stone-900'}`}>{quest.title}</h4>
        <p className="text-xs text-stone-500 mt-1 mb-3">{quest.description}</p>
        
        {quest.isCompleted ? (
            <div className="flex items-center gap-2 text-xs font-bold text-emerald-600">
                <CheckCircle2 size={16} /> Completed
            </div>
        ) : (
             <div className="w-full bg-stone-100 h-1.5 rounded-full overflow-hidden">
                <div className="bg-amber-500 h-full" style={{ width: `${Math.min(100, (quest.currentValue || 0) / (quest.targetValue || 1) * 100)}%` }}></div>
             </div>
        )}
    </div>
);

const LocationCard: React.FC<{ location: any; alerts: Alert[] }> = ({ location, alerts }) => {
    const critical = alerts.filter(a => a.severity === 'critical').length;
    const warning = alerts.filter(a => a.severity === 'warning').length;
    
    let statusColor = 'bg-emerald-500';
    let borderColor = 'border-emerald-200';
    let glow = 'shadow-emerald-500/20';

    if (critical > 0) {
        statusColor = 'bg-rose-500';
        borderColor = 'border-rose-500 ring-2 ring-rose-500/20';
        glow = 'shadow-rose-500/40';
    } else if (warning > 0) {
        statusColor = 'bg-amber-500';
        borderColor = 'border-amber-400';
        glow = 'shadow-amber-500/20';
    }

    return (
        <Link to={`/inventory?loc=${location.id}`} className={`relative bg-white rounded-xl p-5 border-2 ${borderColor} shadow-lg ${glow} hover:-translate-y-1 transition-transform group overflow-hidden`}>
            {critical > 0 && <div className="absolute top-0 right-0 p-2"><div className="w-3 h-3 rounded-full bg-rose-500 animate-ping"></div></div>}
            
            <div className="flex items-center gap-3 mb-4">
                <div className={`w-10 h-10 rounded-lg flex items-center justify-center text-white font-bold shadow-md ${statusColor}`}>
                    {location.name.substring(0, 1)}
                </div>
                <div>
                    <h3 className="font-bold text-stone-900 group-hover:text-amber-600 transition-colors">{location.name}</h3>
                    <p className="text-xs text-stone-500">{location.address}</p>
                </div>
            </div>
            
            <div className="space-y-2">
                {alerts.slice(0, 2).map(alert => (
                    <div key={alert.id} className="text-xs flex items-center gap-2 bg-stone-50 p-2 rounded border border-stone-100">
                        {alert.type === 'STOCKOUT' ? <Package size={12} className="text-rose-500" /> : <AlertOctagon size={12} className="text-amber-500" />}
                        <span className="truncate flex-1 font-medium text-stone-700">{alert.message}</span>
                    </div>
                ))}
                {alerts.length === 0 && (
                    <div className="text-xs text-emerald-600 font-bold flex items-center gap-1 p-2 bg-emerald-50 rounded border border-emerald-100">
                        <CheckCircle2 size={12} /> Systems Normal
                    </div>
                )}
                {alerts.length > 2 && (
                    <div className="text-[10px] text-center text-stone-400 font-bold">
                        +{alerts.length - 2} more issues
                    </div>
                )}
            </div>
        </Link>
    );
};


const Dashboard: React.FC = () => {
  const { inventory, items, locations, currentLocationId, quests, completeQuest } = useApp();
  
  const [alerts, setAlerts] = useState<Alert[]>([]);
  
  useEffect(() => {
    const newAlerts = generateAlerts(inventory, items, locations, 'all'); // Generate all alerts for heat map
    setAlerts(newAlerts);
  }, [inventory, items, locations]);

  return (
    <div className="space-y-8 animate-in fade-in duration-500">
      
      {/* 1. Daily Quests Board */}
      <div>
         <div className="flex items-center gap-2 mb-4">
            <Target className="text-amber-600" size={20} />
            <h2 className="text-lg font-bold text-stone-900 uppercase tracking-wide">Active Directives</h2>
         </div>
         <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {quests.map(quest => (
                <QuestCard key={quest.id} quest={quest} onClaim={completeQuest} />
            ))}
            {/* Filler card if needed */}
            <div className="p-4 rounded-xl border-2 border-dashed border-stone-200 flex flex-col items-center justify-center text-stone-400 min-h-[140px]">
                <MoreHorizontal size={24} className="mb-2 opacity-20" />
                <span className="text-xs font-bold uppercase tracking-widest">New Orders Incoming...</span>
            </div>
         </div>
      </div>

      {/* 2. Operations Heat Map */}
      <div>
         <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2">
                <MapPin className="text-stone-900" size={20} />
                <h2 className="text-lg font-bold text-stone-900 uppercase tracking-wide">Sector Status</h2>
            </div>
            <div className="flex gap-4 text-xs font-bold text-stone-500 uppercase tracking-wider">
                <span className="flex items-center gap-1"><div className="w-2 h-2 rounded-full bg-emerald-500"></div> Stable</span>
                <span className="flex items-center gap-1"><div className="w-2 h-2 rounded-full bg-amber-500"></div> Risk</span>
                <span className="flex items-center gap-1"><div className="w-2 h-2 rounded-full bg-rose-500"></div> Critical</span>
            </div>
         </div>
         
         <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {locations.map(loc => (
                <LocationCard 
                    key={loc.id} 
                    location={loc} 
                    alerts={alerts.filter(a => a.locationId === loc.id)} 
                />
            ))}
         </div>
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-3 gap-8">
        
        {/* Left Column: Alerts Feed & Spike Monitor */}
        <div className="xl:col-span-2 space-y-8">
          
          {/* Spike Monitor (Gamified within component) */}
          <SpikeMonitor />
          
          {/* Urgent Actions List */}
          <div className="bg-stone-900 text-stone-100 rounded-2xl border border-stone-800 shadow-xl overflow-hidden">
             <div className="p-5 border-b border-stone-800 bg-black/20 flex justify-between items-center">
                 <h3 className="font-bold text-white flex items-center gap-2">
                    <Zap size={18} className="text-amber-500 fill-amber-500"/> 
                    Priority Interventions
                 </h3>
                 <span className="text-xs font-bold bg-stone-800 text-stone-300 px-2 py-1 rounded">
                    {alerts.length} Active
                 </span>
             </div>
             
             <div className="divide-y divide-stone-800">
                {alerts.length === 0 ? (
                    <div className="p-12 text-center text-stone-500">
                        No priority alerts. Operations nominal.
                    </div>
                ) : (
                    alerts.slice(0, 5).map(alert => (
                        <div key={alert.id} className="p-4 hover:bg-stone-800/50 transition-colors flex items-center justify-between gap-4 group">
                            <div className="flex items-center gap-3">
                                {alert.severity === 'critical' ? (
                                    <div className="p-2 bg-rose-500/20 text-rose-500 rounded-lg animate-pulse">
                                        <AlertTriangle size={20} />
                                    </div>
                                ) : (
                                    <div className="p-2 bg-amber-500/20 text-amber-500 rounded-lg">
                                        <AlertOctagon size={20} />
                                    </div>
                                )}
                                <div>
                                    <h4 className="font-bold text-sm text-stone-200 group-hover:text-white">{alert.message}</h4>
                                    <p className="text-xs text-stone-500 mt-0.5">{alert.locationName} • {new Date(alert.timestamp).toLocaleTimeString()}</p>
                                </div>
                            </div>
                            
                            {alert.action && (
                                <Link 
                                    to={alert.action.to}
                                    className="px-3 py-1.5 bg-stone-800 hover:bg-amber-600 hover:text-white text-stone-300 text-xs font-bold rounded transition-all border border-stone-700"
                                >
                                    {alert.action.label}
                                </Link>
                            )}
                        </div>
                    ))
                )}
             </div>
          </div>
        </div>

        {/* Right Column: Mini Tools */}
        <div className="space-y-6">
           <div className="bg-white rounded-2xl border border-stone-200 shadow-sm p-6">
              <h3 className="font-bold text-stone-900 mb-4 flex items-center gap-2">
                <Truck size={18} /> Quick Actions
              </h3>
              <div className="grid grid-cols-2 gap-3">
                 <Link to="/ordering" className="p-4 bg-stone-50 hover:bg-amber-50 hover:border-amber-200 border border-stone-100 rounded-xl transition-all flex flex-col items-center gap-2 text-center group">
                    <Truck size={24} className="text-stone-400 group-hover:text-amber-600 transition-colors" />
                    <span className="text-xs font-bold text-stone-600 group-hover:text-stone-900">Restock</span>
                 </Link>
                 <Link to="/transfers" className="p-4 bg-stone-50 hover:bg-blue-50 hover:border-blue-200 border border-stone-100 rounded-xl transition-all flex flex-col items-center gap-2 text-center group">
                    <Activity size={24} className="text-stone-400 group-hover:text-blue-600 transition-colors" />
                    <span className="text-xs font-bold text-stone-600 group-hover:text-stone-900">Balance</span>
                 </Link>
                 <Link to="/inventory" className="p-4 bg-stone-50 hover:bg-emerald-50 hover:border-emerald-200 border border-stone-100 rounded-xl transition-all flex flex-col items-center gap-2 text-center group">
                    <Package size={24} className="text-stone-400 group-hover:text-emerald-600 transition-colors" />
                    <span className="text-xs font-bold text-stone-600 group-hover:text-stone-900">Audit</span>
                 </Link>
                 <Link to="/analytics" className="p-4 bg-stone-50 hover:bg-purple-50 hover:border-purple-200 border border-stone-100 rounded-xl transition-all flex flex-col items-center gap-2 text-center group">
                    <TrendingUp size={24} className="text-stone-400 group-hover:text-purple-600 transition-colors" />
                    <span className="text-xs font-bold text-stone-600 group-hover:text-stone-900">Forecast</span>
                 </Link>
              </div>
           </div>
        </div>

      </div>
    </div>
  );
};

export default Dashboard;