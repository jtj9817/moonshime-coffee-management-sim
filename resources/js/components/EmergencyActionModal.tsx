import React, { useState } from 'react';
import { EmergencyOption, SpikeSignal, Item } from '../types';
import { Clock, DollarSign, AlertTriangle, ArrowRight, CheckCircle2, X, Truck, Zap } from 'lucide-react';

interface EmergencyActionModalProps {
  isOpen: boolean;
  onClose: () => void;
  signal: SpikeSignal;
  item: Item;
  options: EmergencyOption[];
  onSelectOption: (option: EmergencyOption) => void;
}

const EmergencyActionModal: React.FC<EmergencyActionModalProps> = ({
  isOpen,
  onClose,
  signal,
  item,
  options,
  onSelectOption
}) => {
  const [selectedId, setSelectedId] = useState<string | null>(null);

  if (!isOpen) return null;

  const handleConfirm = () => {
    const opt = options.find(o => o.id === selectedId);
    if (opt) onSelectOption(opt);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-stone-900/80 backdrop-blur-sm animate-in fade-in duration-200">
      <div className="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden animate-in zoom-in-95 duration-200 border border-stone-200">
        
        {/* Header - Urgency Theme */}
        <div className="bg-amber-500 p-6 text-white flex justify-between items-start">
           <div>
              <div className="flex items-center gap-2 font-bold uppercase tracking-wider text-xs bg-black/20 px-2 py-1 rounded w-fit mb-2">
                 <Zap size={12} className="fill-current" /> Emergency Response
              </div>
              <h2 className="text-2xl font-bold leading-none">Stockout Imminent</h2>
              <p className="opacity-90 mt-2 text-sm">
                 {item.name} usage is <strong>{signal.multiplier}x</strong> normal pace.
                 <br/>
                 Projected dry at <strong>{signal.shortageAt}</strong>.
              </p>
           </div>
           <button onClick={onClose} className="p-2 hover:bg-white/20 rounded-full transition-colors">
              <X size={20} />
           </button>
        </div>

        <div className="p-6 space-y-6">
           <div className="text-sm text-stone-600 font-medium">
              Select an action plan to mitigate revenue loss:
           </div>

           <div className="space-y-3">
              {options.map(opt => (
                 <div 
                    key={opt.id}
                    onClick={() => setSelectedId(opt.id)}
                    className={`relative border-2 rounded-xl p-4 cursor-pointer transition-all ${
                       selectedId === opt.id 
                          ? 'border-amber-500 bg-amber-50 shadow-md ring-1 ring-amber-500/30' 
                          : 'border-stone-100 hover:border-amber-300 hover:bg-stone-50'
                    }`}
                 >
                    {opt.recommended && (
                       <span className="absolute top-0 right-0 bg-emerald-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-bl-lg rounded-tr-sm uppercase tracking-wide">
                          Recommended
                       </span>
                    )}

                    <div className="flex justify-between items-start">
                       <div className="flex items-center gap-3">
                          <div className={`w-10 h-10 rounded-full flex items-center justify-center ${
                             opt.type === 'COURIER' ? 'bg-amber-100 text-amber-600' :
                             opt.type === 'IGNORE' ? 'bg-stone-100 text-stone-500' : 'bg-blue-100 text-blue-600'
                          }`}>
                             {opt.type === 'COURIER' && <Zap size={20} className="fill-current"/>}
                             {opt.type === 'VENDOR_EXPEDITE' && <Truck size={20} />}
                             {opt.type === 'IGNORE' && <AlertTriangle size={20} />}
                             {opt.type === 'TRANSFER' && <ArrowRight size={20} />}
                          </div>
                          <div>
                             <div className="font-bold text-stone-900">{opt.providerName}</div>
                             <div className="text-xs text-stone-500 flex items-center gap-1">
                                {opt.type === 'COURIER' ? 'Instant Delivery' : opt.type === 'IGNORE' ? 'Revenue Risk' : 'Standard Expedite'}
                             </div>
                          </div>
                       </div>
                       <div className="text-right">
                          <div className="font-bold text-stone-900 text-lg">
                             ${opt.cost.toFixed(0)}
                          </div>
                          <div className={`text-xs font-bold ${opt.etaHours < 2 ? 'text-emerald-600' : 'text-stone-500'}`}>
                             {opt.etaLabel}
                          </div>
                       </div>
                    </div>
                    {opt.riskDescription && (
                       <div className="mt-3 text-xs bg-white/50 p-2 rounded text-stone-500 border border-stone-100 flex items-start gap-1.5">
                          <AlertTriangle size={12} className="mt-0.5 flex-shrink-0 text-amber-500" />
                          {opt.riskDescription}
                       </div>
                    )}
                 </div>
              ))}
           </div>
        </div>

        <div className="p-4 border-t border-stone-100 bg-stone-50 flex justify-end gap-3">
           <button 
              onClick={onClose}
              className="px-4 py-2 rounded-lg text-sm font-bold text-stone-500 hover:bg-stone-200 transition-colors"
           >
              Dismiss
           </button>
           <button 
              onClick={handleConfirm}
              disabled={!selectedId}
              className="px-6 py-2 rounded-lg text-sm font-bold text-white bg-stone-900 hover:bg-stone-800 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg shadow-stone-900/10 flex items-center gap-2 transition-all"
           >
              Execute Plan <ArrowRight size={16} />
           </button>
        </div>

      </div>
    </div>
  );
};

export default EmergencyActionModal;