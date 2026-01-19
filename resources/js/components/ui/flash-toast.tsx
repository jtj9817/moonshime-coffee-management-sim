import { usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle, Info, X } from 'lucide-react';
import { useEffect, useState } from 'react';

export function FlashToast() {
    const { flash } = usePage<any>().props;
    const [visible, setVisible] = useState(false);
    const [msg, setMsg] = useState<{ type: string, text: string } | null>(null);

    useEffect(() => {
        if (flash.success) {
            setMsg({ type: 'success', text: flash.success });
            setVisible(true);
        } else if (flash.error) {
            setMsg({ type: 'error', text: flash.error });
            setVisible(true);
        } else if (flash.warning) {
            setMsg({ type: 'warning', text: flash.warning });
            setVisible(true);
        } else if (flash.info) {
            setMsg({ type: 'info', text: flash.info });
            setVisible(true);
        }
    }, [flash]);

    useEffect(() => {
        if (visible) {
            const timer = setTimeout(() => setVisible(false), 5000);
            return () => clearTimeout(timer);
        }
    }, [visible]);

    if (!visible || !msg) return null;

    const getIcon = () => {
        switch (msg.type) {
            case 'success': return <CheckCircle className="h-5 w-5 text-emerald-500" />;
            case 'error': return <AlertCircle className="h-5 w-5 text-rose-500" />;
            case 'warning': return <AlertCircle className="h-5 w-5 text-amber-500" />;
            default: return <Info className="h-5 w-5 text-blue-500" />;
        }
    };

    const getBorderColor = () => {
        switch (msg.type) {
            case 'success': return 'border-emerald-200 dark:border-emerald-900';
            case 'error': return 'border-rose-200 dark:border-rose-900';
            case 'warning': return 'border-amber-200 dark:border-amber-900';
            default: return 'border-blue-200 dark:border-blue-900';
        }
    };

    return (
        <div className={`fixed top-6 right-6 z-50 flex items-start gap-3 rounded-lg bg-white p-4 shadow-xl border ${getBorderColor()} dark:bg-stone-900 animate-in fade-in slide-in-from-top-2 duration-300 max-w-sm`}>
            {getIcon()}
            <div className="flex-1 pt-0.5">
                <p className="text-sm font-medium text-stone-900 dark:text-stone-100">
                    {msg.text}
                </p>
            </div>
            <button
                onClick={() => setVisible(false)}
                className="text-stone-400 hover:text-stone-600 dark:hover:text-stone-200"
            >
                <X className="h-4 w-4" />
            </button>
        </div>
    );
}
