import { ChevronDown, ChevronRight } from 'lucide-react';
import React, { useState } from 'react';

import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';

interface CollapsibleSectionProps {
    title: string;
    children: React.ReactNode;
    defaultOpen?: boolean;
    className?: string;
}

export function CollapsibleSection({
    title,
    children,
    defaultOpen = true,
    className,
}: CollapsibleSectionProps) {
    const [isOpen, setIsOpen] = useState(defaultOpen);

    return (
        <Collapsible
            open={isOpen}
            onOpenChange={setIsOpen}
            className={cn('space-y-2', className)}
        >
            <div className="flex items-center justify-between space-x-4 px-1">
                <h4 className="text-sm font-semibold text-stone-900 dark:text-stone-100">
                    {title}
                </h4>
                <CollapsibleTrigger asChild>
                    <button className="rounded-sm opacity-70 hover:opacity-100 focus:outline-hidden">
                        {isOpen ? (
                            <ChevronDown className="h-4 w-4" />
                        ) : (
                            <ChevronRight className="h-4 w-4" />
                        )}
                        <span className="sr-only">Toggle</span>
                    </button>
                </CollapsibleTrigger>
            </div>
            <CollapsibleContent className="space-y-2">
                {children}
            </CollapsibleContent>
        </Collapsible>
    );
}
