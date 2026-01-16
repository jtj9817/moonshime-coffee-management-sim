import { router, usePage } from '@inertiajs/react';
import { createContext, ReactNode, useContext, useState } from 'react';

import {
    AlertModel,
    GameShared,
    GameStateShared,
    LocationModel,
    ProductModel,
    SharedData,
    SpikeEventModel,
    VendorModel,
} from '@/types';

interface GameContextType {
    // From Inertia shared props
    gameState: GameStateShared;
    locations: LocationModel[];
    products: ProductModel[];
    vendors: VendorModel[];
    alerts: AlertModel[];
    currentSpike: SpikeEventModel | null;

    // Local state
    currentLocationId: string;
    setCurrentLocationId: (id: string) => void;

    // Actions (will call Inertia routes)
    advanceDay: () => void;
    refreshData: () => void;
    markAlertRead: (alertId: string) => void;
}

const GameContext = createContext<GameContextType | null>(null);

interface GameProviderProps {
    children: ReactNode;
}

export function GameProvider({ children }: GameProviderProps) {
    const { game } = usePage<SharedData>().props;
    const [currentLocationId, setCurrentLocationId] = useState('all');

    if (!game) {
        throw new Error('GameProvider requires authenticated user with game state');
    }

    const advanceDay = () => {
        router.post(
            '/game/advance-day',
            {},
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const refreshData = () => {
        router.reload({ only: ['game'] });
    };

    const markAlertRead = (alertId: string) => {
        router.post(
            `/game/alerts/${alertId}/read`,
            {},
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const value: GameContextType = {
        gameState: game.state,
        locations: game.locations,
        products: game.products,
        vendors: game.vendors,
        alerts: game.alerts,
        currentSpike: game.currentSpike,
        currentLocationId,
        setCurrentLocationId,
        advanceDay,
        refreshData,
        markAlertRead,
    };

    return <GameContext.Provider value={value}>{children}</GameContext.Provider>;
}

export function useGame(): GameContextType {
    const context = useContext(GameContext);
    if (!context) {
        throw new Error('useGame must be used within GameProvider');
    }
    return context;
}

// Optional hook that doesn't throw if game is not available
export function useOptionalGame(): GameContextType | null {
    return useContext(GameContext);
}
