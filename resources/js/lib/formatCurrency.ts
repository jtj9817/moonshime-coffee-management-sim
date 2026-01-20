const currencyFormatter = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

export const formatCurrency = (value: number): string => {
    const amount = Number.isFinite(value) ? value : 0;
    return currencyFormatter.format(amount);
};
