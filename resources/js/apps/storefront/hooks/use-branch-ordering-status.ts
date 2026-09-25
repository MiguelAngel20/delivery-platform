import { useEffect, useState } from 'react';

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
};

const BRANCH_CLOSED_FALLBACK =
    'Esta sucursal está cerrada en este momento. Puedes armar tu pedido, pero no es posible confirmarlo hasta que abra.';

/**
 * Live open/closed status for the branch in the cart (checkout gates).
 * While `isLoading`, continue actions must stay disabled to avoid racing the check.
 */
export function useBranchOrderingStatus(
    branchId?: number | null,
): BranchOrderingGate {
    const [status, setStatus] = useState<BranchOrderingStatus | null>(null);
    const [isLoading, setIsLoading] = useState(
        () => branchId != null && branchId > 0,
    );

    useEffect(() => {
        if (branchId == null || branchId <= 0) {
            setStatus(null);
            setIsLoading(false);

            return;
        }

        const controller = new AbortController();
        setStatus(null);
        setIsLoading(true);

        void (async () => {
            try {
                const response = await fetch(
                    `/cart/branches/${branchId}/ordering-status`,
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        signal: controller.signal,
                    },
                );

                if (!response.ok) {
                    throw new Error('ordering status failed');
                }

                const data = (await response.json()) as {
                    open?: boolean;
                    closed_message?: string | null;
                };

                if (controller.signal.aborted) {
                    return;
                }

                setStatus({
                    open: data.open !== false,
                    closedMessage:
                        data.open === false
                            ? (data.closed_message?.trim() ||
                              BRANCH_CLOSED_FALLBACK)
                            : null,
                });
            } catch {
                if (controller.signal.aborted) {
                    return;
                }

                setStatus(null);
            } finally {
                if (!controller.signal.aborted) {
                    setIsLoading(false);
                }
            }
        })();

        return () => controller.abort();
    }, [branchId]);

    return {
        status,
        isLoading,
        isReady: !isLoading,
    };
}

export { BRANCH_CLOSED_FALLBACK };
