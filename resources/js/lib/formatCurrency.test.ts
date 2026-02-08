import { describe, expect, it } from 'vitest';

import { formatCurrency } from '@/lib/formatCurrency';

describe('formatCurrency', () => {
    it('formats integer cents as dollar display text', () => {
        expect(formatCurrency(150000)).toBe('1,500.00');
        expect(formatCurrency(199)).toBe('1.99');
    });

    it('handles invalid numeric input safely for display-only formatting', () => {
        expect(formatCurrency(Number.NaN)).toBe('0.00');
        expect(formatCurrency(Number.POSITIVE_INFINITY)).toBe('0.00');
    });
});
