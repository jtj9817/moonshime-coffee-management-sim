import { useForm } from '@inertiajs/react';
import { Play } from 'lucide-react';

import { Button } from '@/components/ui/button';

interface AdvanceDayButtonProps {
    className?: string;
    label?: string;
}

export function AdvanceDayButton({ className = '', label = 'Next Day' }: AdvanceDayButtonProps) {
    const { post, processing } = useForm({});

    const handleAdvanceDay = () => {
        post('/game/advance-day', {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <Button
            onClick={handleAdvanceDay}
            disabled={processing}
            size="sm"
            className={`gap-2 bg-amber-600 text-white hover:bg-amber-700 ${className}`}
        >
            {processing ? (
                <>
                    <Play className="h-4 w-4 animate-spin" />
                    <span>Advancing...</span>
                </>
            ) : (
                <>
                    <Play className="h-4 w-4" />
                    <span>{label}</span>
                </>
            )}
        </Button>
    );
}
