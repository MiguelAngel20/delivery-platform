const STORAGE_KEY = 'ride.admin.pending_orders_seen';
const MAX_SEEN = 100;

export function readSeenPendingOrderIds(): number[] {
    if (typeof window === 'undefined') {
        return [];
    }

    try {
        const parsed = JSON.parse(
            window.localStorage.getItem(STORAGE_KEY) ?? '[]',
        ) as unknown;

        if (!Array.isArray(parsed)) {
            return [];
        }

        return parsed
            .map((value) => Number(value))
            .filter((value) => Number.isInteger(value) && value > 0)
            .slice(-MAX_SEEN);
    } catch {
        return [];
    }
}

export function rememberSeenPendingOrders(ids: number[]): number[] {
    const next = [
        ...new Set(ids.filter((id) => Number.isInteger(id) && id > 0)),
    ].slice(-MAX_SEEN);

    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(next));

    return next;
}
