import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import Inventory from '@/pages/game/inventory';
import { setMockGameContext } from '@/tests/support/mocks';
import { renderPage } from '@/tests/support/render-page';

describe('game inventory page', () => {
    it('renders only provided inventory rows and location names from scoped props', () => {
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
        });

        renderPage(
            <Inventory
                currentLocation="all"
                inventory={[
                    {
                        id: 'inv-1',
                        location_id: 'store-1',
                        product_id: 'prod-1',
                        quantity: 45,
                        last_restocked_at: null,
                        location: {
                            id: 'store-1',
                            name: 'Downtown Cafe',
                            type: 'store',
                            address: '1 Main St',
                            max_storage: 1000,
                        },
                        product: {
                            id: 'prod-1',
                            name: 'Arabica Beans',
                            category: 'Beans',
                            is_perishable: false,
                            storage_cost: 25,
                        },
                    },
                ]}
            />,
        );

        expect(screen.getByText('Arabica Beans')).toBeInTheDocument();
        expect(screen.getByText('Downtown Cafe')).toBeInTheDocument();
        expect(screen.getByText('45')).toBeInTheDocument();
    });
});
