import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import Vendors from '@/pages/game/vendors';
import { renderPage } from '@/tests/support/render-page';

describe('game vendors page', () => {
    it('renders vendor metrics from scoped backend props with cents display formatting', () => {
        renderPage(
            <Vendors
                vendors={[
                    {
                        id: 'vendor-1',
                        name: 'Northern Beans',
                        reliability_score: 92,
                        metrics: null,
                        orders_count: 3,
                        orders_avg_total_cost: 98765,
                    },
                ]}
            />,
        );

        expect(screen.getByText('Northern Beans')).toBeInTheDocument();
        expect(screen.getByText('$987.65')).toBeInTheDocument();
        expect(screen.getByText('Excellent')).toBeInTheDocument();
        expect(screen.queryByText('98765')).not.toBeInTheDocument();
    });
});
