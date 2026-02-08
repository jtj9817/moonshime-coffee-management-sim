import { screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import Transfers from '@/pages/game/transfers';
import { setMockGameContext, setNextUseFormState } from '@/tests/support/mocks';
import { renderPage } from '@/tests/support/render-page';

describe('game transfers page', () => {
    it('renders scoped transfer history and suggestion counts from backend props', () => {
        setNextUseFormState({
            data: {
                source_location_id: '',
                target_location_id: '',
                items: [{ product_id: '', quantity: 1 }],
            },
            errors: {},
            processing: false,
            setData: vi.fn(),
            post: vi.fn(),
            reset: vi.fn(),
            clearErrors: vi.fn(),
            transform: vi.fn(),
            put: vi.fn(),
            patch: vi.fn(),
            delete: vi.fn(),
        });

        setMockGameContext({
            locations: [
                {
                    id: 'store-1',
                    name: 'Downtown Cafe',
                    type: 'store',
                    address: '1 Main St',
                    max_storage: 1000,
                },
                {
                    id: 'store-2',
                    name: 'Uptown Cafe',
                    type: 'store',
                    address: '2 Main St',
                    max_storage: 1000,
                },
            ],
            products: [
                {
                    id: 'prod-1',
                    name: 'Arabica Beans',
                    category: 'Beans',
                    is_perishable: false,
                    storage_cost: 20,
                },
            ],
        });

        renderPage(
            <Transfers
                transfers={[
                    {
                        id: '12345678-transfer',
                        source_location_id: 'store-1',
                        target_location_id: 'store-2',
                        status: 'completed',
                        delivery_day: 4,
                        created_at: '2026-02-08T00:00:00Z',
                        source_location: {
                            id: 'store-1',
                            name: 'Downtown Cafe',
                            type: 'store',
                            address: '1 Main St',
                            max_storage: 1000,
                        },
                        target_location: {
                            id: 'store-2',
                            name: 'Uptown Cafe',
                            type: 'store',
                            address: '2 Main St',
                            max_storage: 1000,
                        },
                    },
                ]}
                suggestions={[
                    {
                        from: 'Downtown Cafe',
                        to: 'Uptown Cafe',
                        product: 'Arabica Beans',
                        quantity: 10,
                    },
                ]}
            />,
        );

        expect(screen.getByText('Downtown Cafe')).toBeInTheDocument();
        expect(screen.getByText('Uptown Cafe')).toBeInTheDocument();
        expect(screen.getByText('Transfer History')).toBeInTheDocument();
        expect(screen.getByText('Completed')).toBeInTheDocument();
        expect(screen.getByText('Day 4')).toBeInTheDocument();
    });
});
