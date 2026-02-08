import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import Dashboard from '@/pages/game/dashboard';
import { setMockGameContext } from '@/tests/support/mocks';
import { renderPage } from '@/tests/support/render-page';

describe('game dashboard page', () => {
    it('renders scoped locations and keeps currency formatting at display boundary', () => {
        setMockGameContext({
            locations: [
                {
                    id: 'store-1',
                    name: 'Downtown Cafe',
                    type: 'store',
                    address: '1 Main St',
                    max_storage: 1000,
                },
            ],
            activeSpikes: [],
            gameState: {
                cash: 1_000_000,
                day: 2,
                has_placed_first_order: true,
                level: 1,
                reputation: 70,
                strikes: 0,
                xp: 120,
            },
        });

        renderPage(
            <Dashboard
                alerts={[]}
                kpis={[
                    { label: 'Inventory Value', value: 123456 },
                    { label: 'Orders Placed', value: 4 },
                ]}
                quests={[]}
                logistics_health={95}
                active_spikes_count={0}
                dailyReport={null}
            />,
        );

        expect(screen.getByText('Downtown Cafe')).toBeInTheDocument();
        expect(screen.getByText('$1,234.56')).toBeInTheDocument();
        expect(screen.getByText('4')).toBeInTheDocument();
        expect(screen.queryByText('123456')).not.toBeInTheDocument();
    });
});
