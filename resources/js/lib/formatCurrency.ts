export const formatCurrency = (value: number): string => {
    const amount = Number.isFinite(value) ? value : 0;

    return amount.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};
