import { Progress } from '@/components/ui/progress';

interface RouteCapacityMeterProps {
    currentQuantity: number;
    capacity: number;
    className?: string;
}

export function RouteCapacityMeter({
    currentQuantity,
    capacity,
    className = '',
}: RouteCapacityMeterProps) {
    const percentage = Math.min((currentQuantity / capacity) * 100, 100);

    const getBarColor = () => {
        if (currentQuantity > capacity) return 'bg-rose-500';
        if (percentage > 80) return 'bg-amber-500';
        return 'bg-emerald-500';
    };

    const isOverCapacity = currentQuantity > capacity;

    return (
        <div className={`flex flex-col gap-1.5 ${className}`}>
            <div className="flex justify-between text-xs font-medium">
                <span
                    className={
                        isOverCapacity
                            ? 'text-rose-600 dark:text-rose-400'
                            : 'text-stone-500'
                    }
                >
                    Route Capacity
                </span>
                <span
                    className={
                        isOverCapacity
                            ? 'font-bold text-rose-600'
                            : 'text-stone-700 dark:text-stone-300'
                    }
                >
                    {currentQuantity.toLocaleString()} /{' '}
                    {capacity.toLocaleString()} units
                </span>
            </div>
            <Progress
                value={percentage}
                className="h-2"
                indicatorClassName={getBarColor()}
            />
            {isOverCapacity && (
                <p className="animate-pulse text-[10px] font-bold text-rose-500 uppercase">
                    Capacity Exceeded by{' '}
                    {(currentQuantity - capacity).toLocaleString()} units
                </p>
            )}
        </div>
    );
}
