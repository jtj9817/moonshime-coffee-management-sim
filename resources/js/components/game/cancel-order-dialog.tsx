import { router } from '@inertiajs/react';
import { AlertCircle, RefreshCcw } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { OrderModel } from '@/types';

interface CancelOrderDialogProps {
    order: OrderModel | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function CancelOrderDialog({
    order,
    open,
    onOpenChange,
}: CancelOrderDialogProps) {
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    if (!order) return null;

    const handleConfirm = () => {
        setProcessing(true);
        setError(null);
        router.post(`/game/orders/${order.id}/cancel`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                setProcessing(false);
            },
            onError: (errors) => {
                setProcessing(false);
                // Extract first error message
                const msg = Object.values(errors)[0] || 'Failed to cancel order.';
                setError(msg);
            }
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-rose-600">
                        <AlertCircle className="h-5 w-5" />
                        Cancel Order
                    </DialogTitle>
                    <DialogDescription>
                        Are you sure you want to cancel order <span className="font-mono font-bold text-stone-900 dark:text-stone-100">{order.id.substring(0, 8)}</span>?
                    </DialogDescription>
                </DialogHeader>

                <div className="py-4 space-y-4">
                    <div className="rounded-lg bg-stone-50 p-4 dark:bg-stone-900/50 space-y-2">
                        <div className="flex justify-between text-sm">
                            <span className="text-stone-500">Refund Amount:</span>
                            <span className="font-bold text-emerald-600 font-mono">
                                +${order.total_cost.toLocaleString()}
                            </span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-stone-500">Status Change:</span>
                            <span className="text-stone-700 dark:text-stone-300 font-medium">
                                {order.status} → Cancelled
                            </span>
                        </div>
                    </div>

                    <p className="text-xs text-stone-500 leading-relaxed">
                        Cancellations are immediate. The funds will be returned to your cash balance and the shipment will be halted.
                    </p>
                </div>

                <DialogFooter className="gap-2 sm:gap-0">
                    <Button variant="ghost" onClick={() => onOpenChange(false)} disabled={processing}>
                        Keep Order
                    </Button>
                    <Button
                        onClick={handleConfirm}
                        disabled={processing}
                        className="bg-rose-600 hover:bg-rose-700 text-white gap-2"
                    >
                        {processing ? (
                            <RefreshCcw className="h-4 w-4 animate-spin" />
                        ) : (
                            'Confirm Cancellation'
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
