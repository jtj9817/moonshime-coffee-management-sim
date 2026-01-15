import React, { useState } from 'react';
import { SpikeSignal, Item, SpikeFeedback } from '../types';
import { MessageSquare, CheckCircle2, AlertOctagon, HelpCircle, X, Save } from 'lucide-react';

interface PostMortemModalProps {
  isOpen: boolean;
  onClose: () => void;
  signal: SpikeSignal;
  item: Item;
  onComplete: (feedback: SpikeFeedback) => void;
  defaultIsFalsePositive?: boolean;
}

const PostMortemModal: React.FC<PostMortemModalProps> = ({
  isOpen,
  onClose,
  signal,
  item,
  onComplete,
  defaultIsFalsePositive = false
}) => {
  const [isFalsePositive, setIsFalsePositive] = useState(defaultIsFalsePositive);
  const [classification, setClassification] = useState<SpikeFeedback['classification']>(
    defaultIsFalsePositive ? 'DATA_ERROR' : 'REAL_DEMAND'
  );
  const [rootCause, setRootCause] = useState<SpikeFeedback['rootCause']>('UNKNOWN');
  const [notes, setNotes] = useState('');

  if (!isOpen) return null;

  const handleSubmit = () => {
    onComplete({
      isFalsePositive,
      classification,
      rootCause: isFalsePositive ? undefined : rootCause,
      notes
    });
  };

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-stone-900/60 backdrop-blur-sm animate-in fade-in duration-200">
      <div className="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in zoom-in-95 duration-200 border border-stone-200">
        
        <div className="p-5 border-b border-stone-100 flex justify-between items-center bg-stone-50">
           <h3 className="font-bold text-stone-900 flex items-center gap-2">
              <MessageSquare size={18} className="text-indigo-500" />
              Event Post-mortem
           </h3>
           <button onClick={onClose} className="p-2 hover:bg-stone-200 rounded-full text-stone-500 transition-colors">
              <X size={20} />
           </button>
        </div>

        <div className="p-6 space-y-6">
           <div>
              <p className="text-sm text-stone-600 mb-4">
                 Help refine the detection algorithm. Was this spike for <span className="font-bold text-stone-900">{item.name}</span> a valid anomaly?
              </p>

              <div className="grid grid-cols-2 gap-3">
                 <button 
                    onClick={() => { setIsFalsePositive(false); setClassification('REAL_DEMAND'); }}
                    className={`p-3 rounded-xl border text-sm font-bold flex flex-col items-center gap-2 transition-all ${
                       !isFalsePositive 
                          ? 'bg-indigo-50 border-indigo-500 text-indigo-700 ring-1 ring-indigo-500/20' 
                          : 'bg-white border-stone-200 text-stone-500 hover:border-stone-300'
                    }`}
                 >
                    <CheckCircle2 size={24} />
                    Valid Event
                 </button>
                 <button 
                    onClick={() => { setIsFalsePositive(true); setClassification('DATA_ERROR'); }}
                    className={`p-3 rounded-xl border text-sm font-bold flex flex-col items-center gap-2 transition-all ${
                       isFalsePositive 
                          ? 'bg-rose-50 border-rose-500 text-rose-700 ring-1 ring-rose-500/20' 
                          : 'bg-white border-stone-200 text-stone-500 hover:border-stone-300'
                    }`}
                 >
                    <AlertOctagon size={24} />
                    False Alarm
                 </button>
              </div>
           </div>

           {/* Dynamic Fields based on Type */}
           <div className="space-y-4 animate-in fade-in duration-300">
              
              <div className="space-y-2">
                 <label className="text-xs font-bold text-stone-500 uppercase">Primary Classification</label>
                 <select 
                    value={classification} 
                    onChange={(e) => setClassification(e.target.value as any)}
                    className="w-full p-2.5 bg-stone-50 border border-stone-200 rounded-lg text-sm font-medium outline-none focus:border-indigo-500"
                 >
                    {!isFalsePositive ? (
                       <>
                          <option value="REAL_DEMAND">Real Demand Surge</option>
                          <option value="ONE_OFF_EVENT">One-off Local Event</option>
                          <option value="OPERATIONAL_WASTE">Accidental Spillage/Waste</option>
                       </>
                    ) : (
                       <>
                          <option value="DATA_ERROR">Sensor/Data Error</option>
                          <option value="SYSTEM_GLITCH">Algorithm Sensitivity</option>
                       </>
                    )}
                 </select>
              </div>

              {!isFalsePositive && (
                 <div className="space-y-2">
                    <label className="text-xs font-bold text-stone-500 uppercase">Likely Root Cause</label>
                    <div className="flex flex-wrap gap-2">
                       {['WEATHER', 'LOCAL_EVENT', 'PROMOTION', 'SUPPLY_FAILURE', 'UNKNOWN'].map(cause => (
                          <button
                             key={cause}
                             onClick={() => setRootCause(cause as any)}
                             className={`px-3 py-1.5 rounded-lg text-xs font-bold border transition-colors ${
                                rootCause === cause 
                                   ? 'bg-stone-800 text-white border-stone-800' 
                                   : 'bg-white text-stone-600 border-stone-200 hover:bg-stone-50'
                             }`}
                          >
                             {cause.replace('_', ' ')}
                          </button>
                       ))}
                    </div>
                 </div>
              )}

              <div className="space-y-2">
                 <label className="text-xs font-bold text-stone-500 uppercase">Context Notes</label>
                 <textarea 
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    placeholder="Briefly describe what happened..."
                    className="w-full p-3 bg-stone-50 border border-stone-200 rounded-lg text-sm focus:border-indigo-500 outline-none min-h-[80px]"
                 />
              </div>
           </div>

        </div>

        <div className="p-4 bg-stone-50 border-t border-stone-100 flex justify-end">
           <button 
              onClick={handleSubmit}
              className="px-6 py-2.5 bg-stone-900 text-white rounded-lg font-bold text-sm hover:bg-stone-800 shadow-lg flex items-center gap-2 transition-all"
           >
              <Save size={16} /> Complete & Archive
           </button>
        </div>

      </div>
    </div>
  );
};

export default PostMortemModal;