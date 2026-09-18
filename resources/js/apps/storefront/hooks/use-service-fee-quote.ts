import { useEffect, useState } from 'react';
import {
    COVERAGE_UNAVAILABLE_MESSAGE,
    checkDeliveryCoverage,
} from '@/apps/storefront/lib/check-delivery-coverage';

export type ServiceFeeQuote = {
    serviceFee: number;
    serviceFeeDiscount: number;
    distanceMeters: number | null;
    covered: boolean;
    message: string | null;
};

function csrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Quotes distance-based service fee and platform coverage for delivery lat/lng + branch.
 */
export function useServiceFeeQuote(
    latitude?: number | null,
    longitude?: number | null,
    branchId?: number | null,
): ServiceFeeQuote | null {
    const [quote, setQuote] = useState<ServiceFeeQuote | null>(null);

    useEffect(() => {
        if (
            typeof latitude !== 'number' ||
            typeof longitude !== 'number' ||
            Number.isNaN(latitude) ||
            Number.isNaN(longitude)
        ) {
            setQuote(null);

            return;
        }

        const controller = new AbortController();

        void (async () => {
            try {
                const response = await fetch('/service-fee-quote', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-XSRF-TOKEN': csrfToken(),
                    },
                    credentials: 'same-origin',
                    signal: controller.signal,
                    body: JSON.stringify({
                        latitude,
                        longitude,
                        branch_id: branchId ?? null,
                    }),
                });

                if (!response.ok) {
                    throw new Error('quote failed');
                }

                const data = (await response.json()) as {
                    covered?: boolean;
                    message?: string | null;
                    service_fee: string;
                    service_fee_discount: string;
                    distance_meters: number | null;
                };

                const covered = data.covered !== false;

                setQuote({
                    covered,
                    message: covered
                        ? null
                        : (data.message ?? COVERAGE_UNAVAILABLE_MESSAGE),
                    serviceFee: Number(data.service_fee),
                    serviceFeeDiscount: Number(data.service_fee_discount),
                    distanceMeters: data.distance_meters,
                });
            } catch {
                if (controller.signal.aborted) {
                    return;
                }

                setQuote(null);
            }
        })();

        return () => controller.abort();
    }, [latitude, longitude, branchId]);

    return quote;
}

export { checkDeliveryCoverage, COVERAGE_UNAVAILABLE_MESSAGE };
