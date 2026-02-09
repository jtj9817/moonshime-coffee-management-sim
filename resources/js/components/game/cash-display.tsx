import { DollarSign } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatCurrency } from '@/lib/formatCurrency';

interface CashDisplayProps {
    cash: number;
    className?: string;
}

export function CashDisplay({ cash, className = '' }: CashDisplayProps) {
    const prevCashRef = useRef(cash);
    const [delta, setDelta] = useState<number | null>(null);

    useEffect(() => {
        if (cash !== prevCashRef.current) {
            setDelta(cash - prevCashRef.current);
            prevCashRef.current = cash;
            const timer = setTimeout(() => setDelta(null), 1500);
            return () => clearTimeout(timer);
        }
    }, [cash]);

    const getCashColor = () => {
        if (delta && delta > 0) return 'text-emerald-600 dark:text-emerald-400';
        if (delta && delta < 0) return 'text-rose-600 dark:text-rose-400';
        return 'text-stone-900 dark:text-white';
    };

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    <div className={`flex items-center gap-2 ${className}`}>
                        <div
                            className={`rounded-full p-1 transition-colors duration-300 ${
                                delta && delta > 0
                                    ? 'bg-emerald-100 dark:bg-emerald-900/30'
                                    : delta && delta < 0
                                      ? 'bg-rose-100 dark:bg-rose-900/30'
                                      : ''
                            }`}
                        >
                            <DollarSign
                                className={`h-4 w-4 transition-colors ${delta ? getCashColor() : 'text-stone-400'}`}
                            />
                        </div>

                        <div className="flex flex-col items-end leading-none">
                            <span
                                className={`font-mono font-bold transition-all duration-300 ${getCashColor()} ${delta ? 'scale-105' : 'scale-100'}`}
                            >
                                ${formatCurrency(cash)}
                            </span>
                            {delta && (
                                <span
                                    className={`text-[10px] font-bold ${delta > 0 ? 'text-emerald-600' : 'text-rose-600'}`}
                                >
                                    {delta > 0 ? '+' : ''}
                                    {formatCurrency(delta)}
                                </span>
                            )}
                        </div>
                    </div>
                </TooltipTrigger>
                <TooltipContent>
                    <p>Available Cash</p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
