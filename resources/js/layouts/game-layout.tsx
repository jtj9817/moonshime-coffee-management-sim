import { type ReactNode } from 'react';

import { BetaBanner } from '@/components/game/beta-banner';
import { GameHeader } from '@/components/game/game-header';
import { FlashToast } from '@/components/ui/flash-toast';
import { GameProvider } from '@/contexts/game-context';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem } from '@/types';


interface GameLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

export default function GameLayout({ children, breadcrumbs }: GameLayoutProps) {
    return (
        <GameProvider>
            <BetaBanner />
            <FlashToast />
            <AppSidebarLayout breadcrumbs={breadcrumbs}>
                <GameHeader />
                <div className="flex-1 overflow-auto">{children}</div>
            </AppSidebarLayout>
        </GameProvider>
    );
}
