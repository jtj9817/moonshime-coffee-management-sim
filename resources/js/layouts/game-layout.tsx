import { type ReactNode } from 'react';

import { GameHeader } from '@/components/game/game-header';
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
            <AppSidebarLayout breadcrumbs={breadcrumbs}>
                <GameHeader />
                <div className="flex-1 overflow-auto">{children}</div>
            </AppSidebarLayout>
        </GameProvider>
    );
}
