import { 
  Calendar as CalendarIcon, 
  Search, 
  Filter, 
  TrendingUp, 
  AlertTriangle, 
  CheckCircle2, 
  XCircle, 
  ChevronRight, 
  X,
  MapPin,
  Package,
  Clock,
  Activity,
  Zap,
  DollarSign,
  CloudLightning,
  Megaphone,
  Users,
  HelpCircle,
  Truck
} from 'lucide-react';
import React, { useState, useEffect, useMemo } from 'react';
import { 
  AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, ReferenceLine 
} from 'recharts';

import { useApp } from '../App';
import { generateSpikeHistory } from '../services/spikeService';
import { SpikeHistoryEvent } from '../types';


const SpikeHistory: React.FC = () => {
  const { items, locations } = useApp();
  
  // State
  const [dateRange, setDateRange] = useState({ start: '2023-09-01', end: '2023-10-31' });
  const [historyData, setHistoryData] = useState<SpikeHistoryEvent[]>([]);
  const [selectedEventId, setSelectedEventId] = useState<string | null>(null);
  const [causeFilter, setCauseFilter] = useState<string>('all');

  // Load Mock Data on Mount/Filter Change
  useEffect(() => {
    const start = new Date(dateRange.start);
    const end = new Date(dateRange.end);
    // Regenerate mock data when range changes
    const data = generateSpikeHistory(items, locations, start, end);
    setHistoryData(data);
  }, [dateRange, items, locations]);

  const selectedEvent = useMemo(() => 
    historyData.find(e => e.id === selectedEventId), 
  [historyData, selectedEventId]);

  const filteredHistory = useMemo(() => {
    return historyData.filter(e => causeFilter === 'all' || e.rootCause === causeFilter);
  }, [historyData, causeFilter]);

  const getCauseIcon = (cause: string) => {
    switch (cause) {
      case 'WEATHER': return <CloudLightning size={16} />;
      case 'PROMOTION': return <Megaphone size={16} />;
      case 'LOCAL_EVENT': return <Users size={16} />;
      case 'SUPPLY_FAILURE': return <Truck size={16} />;
      default: return <HelpCircle size={16} />;
    }
  };

  const getCauseLabel = (cause: string) => {
    switch (cause) {
      case 'WEATHER': return 'Weather Event';
      case 'PROMOTION': return 'Marketing Promo';
      case 'LOCAL_EVENT': return 'Local Crowd';
      case 'SUPPLY_FAILURE': return 'Supply Chain Failure';
      default: return 'Unknown Anomaly';
    }
  };

  return (
    <div className="flex flex-col lg:flex-row h-[calc(100vh-140px)] gap-6 animate-in fade-in duration-500 overflow-hidden">
      
      {/* --- LEFT PANEL: List & Filters --- */}
      <div className={`flex flex-col gap-4 transition-all duration-300 ${selectedEvent ? 'lg:w-5/12' : 'lg:w-full'}`}>
        
        {/* Header Control Bar */}
        <div className="bg-white p-4 rounded-xl border border-stone-200 shadow-sm space-y-4">
           <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
              <div>
                 <h2 className="text-xl font-bold text-stone-900 flex items-center gap-2">
                    <Activity className="text-rose-500" /> Spike History
                 </h2>
                 <p className="text-sm text-stone-500">Analyze past demand anomalies and resolutions.</p>
              </div>
              
              <div className="flex items-center gap-2 bg-stone-50 p-1.5 rounded-lg border border-stone-100">
                 <div className="relative">
                    <CalendarIcon size={14} className="absolute left-2 top-1/2 -translate-y-1/2 text-stone-400"/>
                    <input 
                      type="date" 
                      value={dateRange.start}
                      onChange={e => setDateRange(prev => ({ ...prev, start: e.target.value }))}
                      className="pl-7 pr-2 py-1 bg-white border border-stone-200 rounded text-xs font-medium outline-none focus:border-amber-500 w-32"
                    />
                 </div>
                 <span className="text-stone-400">-</span>
                 <div className="relative">
                    <CalendarIcon size={14} className="absolute left-2 top-1/2 -translate-y-1/2 text-stone-400"/>
                    <input 
                      type="date" 
                      value={dateRange.end}
                      onChange={e => setDateRange(prev => ({ ...prev, end: e.target.value }))}
                      className="pl-7 pr-2 py-1 bg-white border border-stone-200 rounded text-xs font-medium outline-none focus:border-amber-500 w-32"
                    />
                 </div>
              </div>
           </div>
           
           {/* Filters */}
           <div className="flex gap-2 overflow-x-auto pb-1 scrollbar-hide">
              <button 
                 onClick={() => setCauseFilter('all')}
                 className={`px-3 py-1.5 rounded-full text-xs font-bold border whitespace-nowrap transition-colors ${causeFilter === 'all' ? 'bg-stone-800 text-white border-stone-800' : 'bg-white text-stone-600 border-stone-200 hover:bg-stone-50'}`}
              >
                 All Causes
              </button>
              {['WEATHER', 'LOCAL_EVENT', 'PROMOTION', 'SUPPLY_FAILURE'].map(c => (
                 <button 
                    key={c}
                    onClick={() => setCauseFilter(c)}
                    className={`px-3 py-1.5 rounded-full text-xs font-bold border whitespace-nowrap flex items-center gap-1.5 transition-colors ${causeFilter === c ? 'bg-stone-800 text-white border-stone-800' : 'bg-white text-stone-600 border-stone-200 hover:bg-stone-50'}`}
                 >
                    {getCauseIcon(c)} {getCauseLabel(c)}
                 </button>
              ))}
           </div>
        </div>

        {/* Event List */}
        <div className="flex-1 overflow-y-auto space-y-3 pr-2 scrollbar-thin pb-20">
           {filteredHistory.length === 0 ? (
              <div className="text-center py-20 text-stone-400">
                 <p>No spike events found in this range.</p>
              </div>
           ) : (
              filteredHistory.map(event => {
                 const item = items.find(i => i.id === event.itemId);
                 const loc = locations.find(l => l.id === event.locationId);
                 const isSelected = selectedEventId === event.id;

                 return (
                    <div 
                       key={event.id}
                       onClick={() => setSelectedEventId(event.id)}
                       className={`border rounded-xl p-4 cursor-pointer transition-all hover:shadow-md ${
                          isSelected ? 'bg-amber-50 border-amber-400 ring-1 ring-amber-400/30' : 'bg-white border-stone-200 hover:border-amber-200'
                       }`}
                    >
                       <div className="flex justify-between items-start">
                          <div className="flex items-center gap-3">
                             <div className={`w-10 h-10 rounded-full flex items-center justify-center border ${
                                event.rootCause === 'WEATHER' ? 'bg-blue-50 text-blue-500 border-blue-100' :
                                event.rootCause === 'PROMOTION' ? 'bg-purple-50 text-purple-500 border-purple-100' :
                                'bg-stone-50 text-stone-500 border-stone-100'
                             }`}>
                                {getCauseIcon(event.rootCause)}
                             </div>
                             <div>
                                <div className="font-bold text-stone-900 text-sm flex items-center gap-2">
                                   {new Date(event.date).toLocaleDateString(undefined, { month: 'short', day: 'numeric'})}
                                   <span className="text-stone-300">•</span>
                                   <span className="text-stone-600 font-medium">{event.timeDetected}</span>
                                </div>
                                <div className="text-xs text-stone-500 mt-0.5 flex items-center gap-1">
                                   <MapPin size={10} /> {loc?.name}
                                </div>
                             </div>
                          </div>
                          <div className="text-right">
                             <div className="font-bold text-rose-600 text-lg leading-none">{event.peakMultiplier}x</div>
                             <div className="text-[10px] text-stone-400 font-medium uppercase mt-1">Peak Demand</div>
                          </div>
                       </div>
                       
                       <div className="mt-3 pt-3 border-t border-stone-100/50 flex justify-between items-center">
                          <div className="flex items-center gap-2 text-xs font-bold text-stone-700">
                             <Package size={12} className="text-stone-400" />
                             {item?.name}
                          </div>
                          <div className={`text-[10px] font-bold px-2 py-0.5 rounded flex items-center gap-1 ${
                             event.status === 'RESOLVED' ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-500'
                          }`}>
                             {event.status === 'RESOLVED' ? <CheckCircle2 size={10}/> : <XCircle size={10}/>}
                             {event.status}
                          </div>
                       </div>
                    </div>
                 );
              })
           )}
        </div>
      </div>

      {/* --- RIGHT PANEL: Inspection Detail --- */}
      {selectedEvent && (
         <div className="lg:w-7/12 bg-white rounded-2xl border border-stone-200 shadow-xl overflow-hidden flex flex-col animate-in slide-in-from-right-10 duration-300 absolute inset-0 lg:static z-20">
            {/* Detail Header */}
            <div className="p-6 border-b border-stone-100 flex justify-between items-start bg-stone-50">
               <div>
                  <div className="flex items-center gap-2 mb-2">
                     <span className="bg-stone-900 text-white text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider">
                        Analysis View
                     </span>
                     <span className="text-xs text-stone-500 font-mono">
                        ID: {selectedEvent.id}
                     </span>
                  </div>
                  <h2 className="text-2xl font-bold text-stone-900">
                     {items.find(i => i.id === selectedEvent.itemId)?.name} Spike
                  </h2>
                  <div className="flex items-center gap-4 text-xs mt-2 text-stone-600">
                     <span className="flex items-center gap-1"><CalendarIcon size={12}/> {selectedEvent.date}</span>
                     <span className="flex items-center gap-1"><Clock size={12}/> Duration: {selectedEvent.durationHours}h</span>
                     <span className="flex items-center gap-1"><MapPin size={12}/> {locations.find(l => l.id === selectedEvent.locationId)?.name}</span>
                  </div>
               </div>
               <button 
                  onClick={() => setSelectedEventId(null)}
                  className="p-2 hover:bg-stone-200 rounded-full text-stone-500 transition-colors"
               >
                  <X size={20} />
               </button>
            </div>
            
            <div className="flex-1 overflow-y-auto p-6 space-y-6">
               
               {/* 1. Root Cause Section */}
               <div className="grid grid-cols-2 gap-4">
                  <div className="p-4 bg-amber-50 rounded-xl border border-amber-100">
                     <h4 className="text-xs font-bold text-amber-800 uppercase mb-2 flex items-center gap-2">
                        <Zap size={14}/> Identified Root Cause
                     </h4>
                     <div className="flex items-center gap-3">
                        <div className="p-2 bg-white rounded-lg border border-amber-100 text-amber-600">
                           {getCauseIcon(selectedEvent.rootCause)}
                        </div>
                        <div>
                           <div className="font-bold text-stone-900">{getCauseLabel(selectedEvent.rootCause)}</div>
                           <div className="text-xs text-stone-500">Confidence Score: High</div>
                        </div>
                     </div>
                  </div>
                  <div className="p-4 bg-stone-50 rounded-xl border border-stone-100">
                     <h4 className="text-xs font-bold text-stone-500 uppercase mb-2 flex items-center gap-2">
                        <DollarSign size={14}/> Financial Impact
                     </h4>
                     <div className="flex items-center gap-3">
                        <div className="text-2xl font-bold text-stone-900">
                           ${selectedEvent.totalImpactCost.toFixed(2)}
                        </div>
                        <div className="text-[10px] text-stone-400 leading-tight">
                           Total cost incurred <br/>(Expedite fees + Lost Sales)
                        </div>
                     </div>
                  </div>
               </div>

               {/* 2. Chart Section */}
               <div className="h-64 w-full bg-white rounded-xl border border-stone-100 p-4">
                  <h4 className="text-xs font-bold text-stone-500 uppercase mb-4">Demand Velocity Curve (Units/Hour)</h4>
                  <ResponsiveContainer width="100%" height="100%">
                     <AreaChart data={selectedEvent.chartData}>
                        <defs>
                           <linearGradient id="colorSpike" x1="0" y1="0" x2="0" y2="1">
                              <stop offset="5%" stopColor="#f43f5e" stopOpacity={0.8}/>
                              <stop offset="95%" stopColor="#f43f5e" stopOpacity={0}/>
                           </linearGradient>
                        </defs>
                        <XAxis dataKey="time" stroke="#a8a29e" fontSize={10} tickLine={false} axisLine={false} />
                        <YAxis stroke="#a8a29e" fontSize={10} tickLine={false} axisLine={false} />
                        <Tooltip 
                           contentStyle={{ borderRadius: '8px', border: 'none', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)', fontSize: '12px' }}
                        />
                        <ReferenceLine y={selectedEvent.chartData[0].baseline} label="Baseline" stroke="#a8a29e" strokeDasharray="3 3" fontSize={10}/>
                        <Area type="monotone" dataKey="value" stroke="#f43f5e" strokeWidth={2} fill="url(#colorSpike)" name="Demand" />
                        <Area type="monotone" dataKey="baseline" stroke="none" fill="#e7e5e4" fillOpacity={0.2} name="Baseline" />
                     </AreaChart>
                  </ResponsiveContainer>
               </div>

               {/* 3. Resolution Timeline Log */}
               <div>
                  <h4 className="text-xs font-bold text-stone-500 uppercase mb-4">Resolution Timeline</h4>
                  <div className="space-y-4 pl-4 border-l-2 border-stone-100 ml-2">
                     {/* Detection Node */}
                     <div className="relative">
                        <div className="absolute -left-[21px] top-1 w-3 h-3 bg-rose-500 rounded-full border-2 border-white"></div>
                        <div className="flex justify-between items-start">
                           <div>
                              <div className="text-sm font-bold text-stone-900">Anomaly Detected</div>
                              <div className="text-xs text-stone-500">System triggered alerts. Rate exceeded {selectedEvent.peakMultiplier}x baseline.</div>
                           </div>
                           <div className="text-xs font-mono text-stone-400">{selectedEvent.timeDetected}</div>
                        </div>
                     </div>

                     {/* Action Logs */}
                     {selectedEvent.resolutionLog.map((log, idx) => (
                        <div key={idx} className="relative">
                           <div className="absolute -left-[21px] top-1 w-3 h-3 bg-emerald-500 rounded-full border-2 border-white"></div>
                           <div className="flex justify-between items-start">
                              <div>
                                 <div className="text-sm font-bold text-stone-900">{log.action}</div>
                                 <div className="text-xs text-stone-500">{log.note}</div>
                                 <div className="mt-1 flex items-center gap-2 text-[10px] text-stone-400 bg-stone-50 w-fit px-1.5 py-0.5 rounded">
                                    <Users size={10}/> {log.user}
                                    <span className="w-px h-2 bg-stone-300"></span>
                                    <span>-${log.costIncurred}</span>
                                 </div>
                              </div>
                              <div className="text-xs font-mono text-stone-400">{log.timestamp}</div>
                           </div>
                        </div>
                     ))}
                  </div>
               </div>

            </div>
            
            <div className="p-4 bg-stone-50 border-t border-stone-200 text-right">
               <button 
                  onClick={() => setSelectedEventId(null)}
                  className="px-4 py-2 bg-white border border-stone-200 rounded-lg text-sm font-bold text-stone-600 hover:bg-stone-100"
               >
                  Close Detail
               </button>
            </div>
         </div>
      )}
    </div>
  );
};

export default SpikeHistory;