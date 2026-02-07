const currencyFormatter = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

/**
 * Format an integer cents value as a dollar string (e.g. 150000 → "1,500.00").
 * All monetary values from the backend are in integer cents.
 */
export const formatCurrency = (cents: number): string => {
    const safeCents = Number.isFinite(cents) ? cents : 0;
    return currencyFormatter.format(safeCents / 100);
};
