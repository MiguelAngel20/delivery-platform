export function formatMoney(
    amount: number | string | null | undefined,
): string {
    const value = Number(amount ?? 0);

    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number.isFinite(value) ? value : 0);
}
