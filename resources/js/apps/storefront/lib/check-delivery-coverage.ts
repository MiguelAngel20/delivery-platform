export const COVERAGE_UNAVAILABLE_MESSAGE =
    'Ups, lo sentimos. Aún no tenemos cobertura para tu zona. Pronto llegaremos a tu zona.';

function csrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export type CoverageCheckResult = {
    covered: boolean;
    message: string | null;
};

/**
 * Platform coverage check for a delivery point (optional branch restriction).
 */
export async function checkDeliveryCoverage(
    latitude: number,
    longitude: number,
    branchId?: number | null,
    signal?: AbortSignal,
): Promise<CoverageCheckResult> {
    const response = await fetch('/service-fee-quote', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
        signal,
        body: JSON.stringify({
            latitude,
            longitude,
            branch_id: branchId ?? null,
        }),
    });

    if (!response.ok) {
        throw new Error('coverage check failed');
    }

    const data = (await response.json()) as {
        covered?: boolean;
        message?: string | null;
    };

    const covered = data.covered !== false;

    return {
        covered,
        message: covered
            ? null
            : (data.message ?? COVERAGE_UNAVAILABLE_MESSAGE),
    };
}
