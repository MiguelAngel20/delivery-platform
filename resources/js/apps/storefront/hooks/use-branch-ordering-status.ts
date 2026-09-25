import { useCallback, useEffect, useState } from 'react';

export type BranchOrderingStatus = {
    open: boolean;
    closedMessage: string | null;
};

export type BranchOrderingGate = {
    status: BranchOrderingStatus | null;
    /** True while the live open/closed check is in flight for the current branch. */
    isLoading: boolean;
    /** True once the check finished (or no branch to check). */
    isReady: boolean;
    /** Resolves with the latest status (uses cache / shared in-flight request). */
    waitUntilReady: () => Promise<BranchOrderingStatus | null>;
};

const BRANCH_CLOSED_FALLBACK =
    'Esta sucursal está cerrada en este momento. Puedes armar tu pedido, pero no es posible confirmarlo hasta que abra.';

const CACHE_TTL_MS = 45_000;

type CacheEntry = {
    status: BranchOrderingStatus;
    fetchedAt: number;
};

const statusCache = new Map<number, CacheEntry>();
const inflightRequests = new Map<
    number,
    Promise<BranchOrderingStatus | null>
>();

function readCache(branchId: number): BranchOrderingStatus | null {
    const entry = statusCache.get(branchId);

    if (!entry) {
        return null;
    }

    if (Date.now() - entry.fetchedAt > CACHE_TTL_MS) {
        statusCache.delete(branchId);

        return null;
    }

    return entry.status;
}

async function fetchBranchOrderingStatus(
    branchId: number,
): Promise<BranchOrderingStatus | null> {
    const cached = readCache(branchId);

    if (cached) {
        return cached;
    }

    const existing = inflightRequests.get(branchId);

    if (existing) {
        return existing;
    }

    const request = (async (): Promise<BranchOrderingStatus | null> => {
        try {
            const response = await fetch(
                `/cart/branches/${branchId}/ordering-status`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                },
            );

            if (!response.ok) {
                throw new Error('ordering status failed');
            }

            const data = (await response.json()) as {
                open?: boolean;
                closed_message?: string | null;
            };

            const status: BranchOrderingStatus = {
                open: data.open !== false,
                closedMessage:
                    data.open === false
                        ? (data.closed_message?.trim() ||
                          BRANCH_CLOSED_FALLBACK)
                        : null,
            };

            statusCache.set(branchId, {
                status,
                fetchedAt: Date.now(),
            });

            return status;
        } catch {
            return null;
        } finally {
            inflightRequests.delete(branchId);
        }
    })();

    inflightRequests.set(branchId, request);

    return request;
}

/**
 * Live open/closed status for the branch in the cart (checkout gates).
 * Prefer keeping Continuar enabled and calling `waitUntilReady` on click
 * instead of disabling the button while the check is in flight.
 */
export function useBranchOrderingStatus(
    branchId?: number | null,
): BranchOrderingGate {
    const [status, setStatus] = useState<BranchOrderingStatus | null>(() =>
        branchId != null && branchId > 0 ? readCache(branchId) : null,
    );
    const [isLoading, setIsLoading] = useState(() => {
        if (branchId == null || branchId <= 0) {
            return false;
        }

        return readCache(branchId) == null;
    });

    useEffect(() => {
        if (branchId == null || branchId <= 0) {
            setStatus(null);
            setIsLoading(false);

            return;
        }

        const cached = readCache(branchId);

        if (cached) {
            setStatus(cached);
            setIsLoading(false);

            return;
        }

        let cancelled = false;
        setStatus(null);
        setIsLoading(true);

        void fetchBranchOrderingStatus(branchId).then((result) => {
            if (cancelled) {
                return;
            }

            setStatus(result);
            setIsLoading(false);
        });

        return () => {
            cancelled = true;
        };
    }, [branchId]);

    const waitUntilReady = useCallback(async (): Promise<BranchOrderingStatus | null> => {
        if (branchId == null || branchId <= 0) {
            return null;
        }

        return fetchBranchOrderingStatus(branchId);
    }, [branchId]);

    return {
        status,
        isLoading,
        isReady: !isLoading,
        waitUntilReady,
    };
}

export { BRANCH_CLOSED_FALLBACK };
