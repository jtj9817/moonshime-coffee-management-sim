import { Calendar } from 'lucide-react';
import { useEffect, useState } from 'react';

interface DayCounterProps {
    day: number;
    totalDays?: number;
    className?: string;
}

export function DayCounter({ day, totalDays = 5, className = '' }: DayCounterProps) {
    const [highlight, setHighlight] = useState(false);
    const [prevDay, setPrevDay] = useState(day);

    // Detect day change during render (React-recommended pattern)
    if (day !== prevDay) {
        setPrevDay(day);
        setHighlight(true);
    }

    // Auto-dismiss highlight after 500ms
    useEffect(() => {
        if (highlight) {
            const timer = setTimeout(() => setHighlight(false), 500);
            return () => clearTimeout(timer);
        }
    }, [highlight]);

    return (
        <div
            className={`flex items-center gap-2 rounded-lg border px-3 py-1.5 transition-colors duration-500 ${highlight
                    ? 'border-amber-400 bg-amber-50 dark:border-amber-600 dark:bg-amber-900/40'
                    : 'border-stone-200 bg-white dark:border-stone-700 dark:bg-stone-800'
                } ${className}`}
            role="status"
            aria-live="polite"
        >
            <Calendar className={`h-4 w-4 transition-colors duration-500 ${highlight ? 'text-amber-600 dark:text-amber-400' : 'text-stone-400'}`} />
            <span className="font-mono font-bold text-stone-900 dark:text-white">
                Day {day} <span className="text-stone-400 font-normal">of {totalDays}</span>
            </span>
        </div>
    );
}
