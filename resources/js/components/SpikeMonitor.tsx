import { Activity, Zap, TrendingUp, AlertCircle, RefreshCw, XCircle } from 'lucide-react';
import React, { useState, useEffect } from 'react';

import { useApp } from '../App';
import { detectSpikes, getEmergencyOptions } from '../services/spikeService';
import { SpikeSignal, EmergencyOption, SpikeFeedback } from '../types';

import EmergencyActionModal from './EmergencyActionModal';
import PostMortemModal from './PostMortemModal';

const SpikeMonitor: React.FC = () => {
  const { locations, items, inventory, currentLocationId } = useApp();
  
  // State
  const [signals, setSignals] = useState<SpikeSignal[]>([]);
  const [activeSignal, setActiveSignal] = useState<SpikeSignal | null>(null);
  
  // Modals
  const [actionModalOpen, setActionModalOpen] = useState(false);
  const [postMortemOpen, setPostMortemOpen] = useState(false);
  const [emergencyOptions, setEmergencyOptions] = useState<EmergencyOption[]>([]);
  
  // Flow State
  const [pendingPostMortemSignal, setPendingPostMortemSignal] = useState<SpikeSignal | null>(null);
  const [isFalsePositiveFlow, setIsFalsePositiveFlow] = useState(false);
  const [isLive, setIsLive] = useState(true);

  // Polling Effect
  useEffect(() => {
    if (!isLive) return;

    const interval = setInterval(() => {
      const newSpikes = detectSpikes(locations, items, inventory);
      if (newSpikes.length > 0) {
        setSignals(prev => {
            return [...newSpikes, ...prev].slice(0, 5);
        });
      }
    }, 5000); 

    return () => clearInterval(interval);
  }, [isLive, locations, items, inventory]);

  // 1. Start Resolution Flow
  const handleResolve = (signal: SpikeSignal) => {
    const item = items.find(i => i.id === signal.skuId);
    if (!item) return;

    const options = getEmergencyOptions(signal, item);
    setEmergencyOptions(options);
    setActiveSignal(signal);
    setActionModalOpen(true);
    setIsLive(false); 
  };

  // 1b. Quick Dismiss / False Positive
  const handleQuickFalsePositive = (signal: SpikeSignal) => {
     setActiveSignal(signal);
     setPendingPostMortemSignal(signal);
     setIsFalsePositiveFlow(true);
     setPostMortemOpen(true);
     setIsLive(false);
  };

  // 2. Action Taken -> Move to Post Mortem
  const handleActionComplete = (option: EmergencyOption) => {
    console.log(`Executed Emergency Option: ${option.type} via ${option.providerName}`);
    
    // Close Action Modal
    setActionModalOpen(false);
    
    // Queue Post Mortem
    if (activeSignal) {
       setPendingPostMortemSignal(activeSignal);
       setIsFalsePositiveFlow(false); // Action was taken, so likely not a false alarm initially perceived
       setPostMortemOpen(true);
    }
  };

  // 3. Post Mortem Complete -> Cleanup
  const handlePostMortemComplete = (feedback: SpikeFeedback) => {
     console.log('Spike Feedback Captured:', feedback);
     // In a real app, send to backend here to refine model
     
     // Remove processed signal
     if (pendingPostMortemSignal) {
        setSignals(prev => prev.filter(s => s.id !== pendingPostMortemSignal.id));
     }

     // Reset UI
     setPostMortemOpen(false);
     setPendingPostMortemSignal(null);
     setActiveSignal(null);
     setIsLive(true);
  };

  const handleDismissActionModal = () => {
    setActionModalOpen(false);
    setIsLive(true);
  };

  const handleDismissPostMortem = () => {
     // If they cancel post-mortem, still clear the signal from "active" view 
     // or maybe keep it? Let's assume we clear it but log nothing.
     if (pendingPostMortemSignal) {
        setSignals(prev => prev.filter(s => s.id !== pendingPostMortemSignal.id));
     }
     setPostMortemOpen(false);
     setPendingPostMortemSignal(null);
     setActiveSignal(null);
     setIsLive(true);
  };

  if (signals.length === 0) {
     return (
        <div className="bg-white rounded-xl border border-stone-200 p-4 flex items-center gap-3 text-stone-400">
           <div className="relative">
              <Activity size={20} />
              <div className="absolute top-0 right-0 w-1.5 h-1.5 bg-emerald-400 rounded-full animate-ping"></div>
           </div>
           <div className="text-xs font-medium">Monitoring real-time consumption...</div>
        </div>
     );
  }

  // Filter signals by current location view if specific
  const visibleSignals = currentLocationId === 'all' 
    ? signals 
    : signals.filter(s => s.locationId === currentLocationId);

  if (visibleSignals.length === 0) return null;

  return (
    <>
      <div className="bg-rose-50 border border-rose-100 rounded-xl overflow-hidden animate-in fade-in slide-in-from-top-2">
         {/* Live Header */}
         <div className="bg-rose-100/50 px-4 py-2 flex justify-between items-center border-b border-rose-100">
            <div className="flex items-center gap-2">
               <span className="relative flex h-2 w-2">
                 <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                 <span className="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
               </span>
               <span className="text-xs font-bold text-rose-700 uppercase tracking-wider">Spike Detected</span>
            </div>
            <button onClick={() => setSignals([])} className="text-[10px] text-rose-500 hover:text-rose-800 underline">
               Dismiss All
            </button>
         </div>

         {/* Signal List */}
         <div className="divide-y divide-rose-100">
            {visibleSignals.map(signal => {
               const item = items.find(i => i.id === signal.skuId);
               const location = locations.find(l => l.id === signal.locationId);
               
               return (
                  <div key={signal.id} className="p-4 flex items-center justify-between gap-4">
                     <div className="flex items-center gap-3">
                        <div className="p-2 bg-white rounded-lg border border-rose-100 shadow-sm text-rose-600">
                           <TrendingUp size={20} />
                        </div>
                        <div>
                           <div className="flex items-center gap-2">
                              <span className="text-sm font-bold text-stone-900">{item?.name}</span>
                              <span className="text-[10px] bg-rose-200 text-rose-800 px-1.5 rounded font-bold">{signal.multiplier}x Pace</span>
                           </div>
                           <div className="text-xs text-stone-500 mt-0.5">
                              {location?.name} • <span className="text-rose-600 font-semibold">Dry by {signal.shortageAt}</span>
                           </div>
                        </div>
                     </div>
                     
                     <div className="flex gap-2">
                        <button 
                           onClick={() => handleQuickFalsePositive(signal)}
                           className="px-3 py-1.5 border border-rose-200 text-rose-600 hover:bg-rose-100 text-xs font-bold rounded-lg transition-colors flex items-center gap-1.5"
                           title="Mark as False Alarm"
                        >
                           <XCircle size={14} />
                        </button>
                        <button 
                           onClick={() => handleResolve(signal)}
                           className="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-lg shadow-sm shadow-rose-200 transition-colors whitespace-nowrap flex items-center gap-1.5"
                        >
                           <Zap size={12} className="fill-white" />
                           Act Now
                        </button>
                     </div>
                  </div>
               );
            })}
         </div>
      </div>

      {/* 1. Action Modal */}
      {activeSignal && items.find(i => i.id === activeSignal.skuId) && (
        <EmergencyActionModal 
          isOpen={actionModalOpen}
          onClose={handleDismissActionModal}
          signal={activeSignal}
          item={items.find(i => i.id === activeSignal.skuId)!}
          options={emergencyOptions}
          onSelectOption={handleActionComplete}
        />
      )}

      {/* 2. Post Mortem Modal */}
      {pendingPostMortemSignal && items.find(i => i.id === pendingPostMortemSignal.skuId) && (
         <PostMortemModal 
            isOpen={postMortemOpen}
            onClose={handleDismissPostMortem}
            signal={pendingPostMortemSignal}
            item={items.find(i => i.id === pendingPostMortemSignal.skuId)!}
            onComplete={handlePostMortemComplete}
            defaultIsFalsePositive={isFalsePositiveFlow}
         />
      )}
    </>
  );
};

export default SpikeMonitor;