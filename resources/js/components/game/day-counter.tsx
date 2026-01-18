import { Calendar } from 'lucide-react';

interface DayCounterProps {
    day: number;
    totalDays?: number;
    className?: string;
}

export function DayCounter({ day, totalDays = 30, className = '' }: DayCounterProps) {
    return (
        <div className={`flex items-center gap-2 rounded-lg border border-stone-200 bg-white px-3 py-1.5 dark:border-stone-700 dark:bg-stone-800 ${className}`}>
            <Calendar className="h-4 w-4 text-stone-400" />
            <span className="font-mono font-bold text-stone-900 dark:text-white">
                Day {day} <span className="text-stone-400 font-normal">of {totalDays}</span>
            </span>
        </div>
    );
}
