import { DollarSign } from 'lucide-react';
import { useEffect, useState } from 'react';

import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

interface CashDisplayProps {
    cash: number;
    className?: string;
}

export function CashDisplay({ cash, className = '' }: CashDisplayProps) {
    const [prevCash, setPrevCash] = useState(cash);
    const [isChanged, setIsChanged] = useState(false);

    useEffect(() => {
        if (cash !== prevCash) {
            setIsChanged(true);
            const timer = setTimeout(() => setIsChanged(false), 1000);
            setPrevCash(cash);
            return () => clearTimeout(timer);
        }
    }, [cash, prevCash]);

    const getCashColor = (amount: number) => {
        if (amount > 500000) return 'text-emerald-600 dark:text-emerald-400';
        if (amount > 100000) return 'text-amber-600 dark:text-amber-400';
        return 'text-rose-600 dark:text-rose-400';
    };

    const formatCash = (amount: number) => {
        if (amount >= 1000000) return `$${(amount / 1000000).toFixed(2)}M`;
        if (amount >= 1000) return `$${(amount / 1000).toFixed(1)}K`;
        return `$${amount.toFixed(2)}`;
    };

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    <div className={`flex items-center gap-2 ${className}`}>
                        <div className={`rounded-full p-1 ${isChanged ? 'bg-yellow-100 dark:bg-yellow-900/30 transition-colors duration-500' : ''}`}>
                             <DollarSign className="h-4 w-4 text-stone-400" />
                        </div>
                       
                        <span 
                            className={`font-mono font-bold transition-all duration-300 ${getCashColor(cash)} ${isChanged ? 'scale-110' : 'scale-100'}`}
                        >
                            {formatCash(cash)}
                        </span>
                    </div>
                </TooltipTrigger>
                <TooltipContent>
                    <p>Available Cash</p>
                    <p className="text-xs text-stone-400">
                        ${cash.toLocaleString()}
                    </p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
