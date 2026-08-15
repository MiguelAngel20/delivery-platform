import { useConnectionStatus } from '@laravel/echo-react';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef } from 'react';
import { usePrivateChannelEvents } from '@/hooks/realtime/use-private-channel-events';
import type { OrderRealtimePayload } from '@/lib/realtime/types';

const SOUND_KEY = 'ride.business.new_order_sound';

function playNewOrderChime(): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (window.localStorage.getItem(SOUND_KEY) === '0') {
        return;
    }

    try {
        const AudioCtx =
            window.AudioContext ||
            (window as unknown as { webkitAudioContext?: typeof AudioContext })
                .webkitAudioContext;

        if (!AudioCtx) {
            return;
        }

        const ctx = new AudioCtx();
        const oscillator = ctx.createOscillator();
        const gain = ctx.createGain();
        oscillator.type = 'sine';
        oscillator.frequency.value = 880;
        gain.gain.value = 0.03;
        oscillator.connect(gain);
        gain.connect(ctx.destination);
        oscillator.start();
        oscillator.stop(ctx.currentTime + 0.12);
        window.setTimeout(() => void ctx.close(), 200);
    } catch {
        // Browsers may block audio without a prior user gesture.
    }
}

type Options = {
    businessId?: number | null;
    branchIds?: number[];
    only?: string[];
    playSoundOnCreate?: boolean;
};

export function useBusinessOrderEvents({
    businessId,
    branchIds = [],
    only = ['orders', 'newCount', 'order'],
    playSoundOnCreate = true,
}: Options): void {
    const reload = useCallback(() => {
        router.reload({
            only,
            preserveUrl: true,
            preserveScroll: true,
        });
    }, [only.join('|')]);

    const onEvent = useCallback(
        (event: string, _payload: OrderRealtimePayload) => {
            if (playSoundOnCreate && event === '.OrderCreated') {
                playNewOrderChime();
            }

            reload();
        },
        [playSoundOnCreate, reload],
    );

    usePrivateChannelEvents({
        channels: [
            businessId ? `business.${businessId}` : null,
            ...branchIds.map((id) => `branch.${id}`),
        ],
        events: ['.OrderCreated', '.OrderStatusChanged', '.DriverAssigned', '.IncidentCreated'],
        enabled: Boolean(businessId) || branchIds.length > 0,
        onEvent,
    });

    useRealtimeReconnect(reload);
}

export function useCustomerOrderEvents(customerId?: number | null, orderId?: number | null): void {
    const reload = useCallback(() => {
        router.reload({
            only: ['order', 'orders'],
            preserveUrl: true,
            preserveScroll: true,
        });
    }, []);

    usePrivateChannelEvents({
        channels: [
            customerId ? `customer.${customerId}` : null,
            orderId ? `order.${orderId}` : null,
        ],
        events: ['.OrderStatusChanged', '.DriverAssigned', '.IncidentCreated', '.CustomOrderQuoteCreated', '.CustomOrderConverted'],
        enabled: Boolean(customerId || orderId),
        onEvent: () => reload(),
    });

    useRealtimeReconnect(reload);
}

export function useDriverOrderEvents(driverId?: number | null, branchIds: number[] = []): void {
    const reload = useCallback(() => {
        router.reload({
            only: [
                'availableOrders',
                'activeOrders',
                'activeGroups',
                'compatibleOrders',
                'stats',
                'availabilityStatus',
            ],
            preserveUrl: true,
            preserveScroll: true,
        });
    }, []);

    usePrivateChannelEvents({
        channels: [
            driverId ? `driver.${driverId}` : null,
            ...branchIds.map((id) => `branch.${id}.offers`),
        ],
        events: [
            '.OrderAvailable',
            '.OrderStatusChanged',
            '.DriverAssigned',
            '.IncidentCreated',
        ],
        enabled: Boolean(driverId),
        onEvent: () => reload(),
    });

    useRealtimeReconnect(reload);
}

export function useAdminOrderEvents(
    enabled = true,
    only: string[] = ['orders', 'order'],
): void {
    const reload = useCallback(() => {
        router.reload({
            only,
            preserveUrl: true,
            preserveScroll: true,
        });
    }, [only.join('|')]);

    usePrivateChannelEvents({
        channels: ['admin'],
        events: [
            '.OrderCreated',
            '.DriverAssigned',
            '.OrderStatusChanged',
            '.IncidentCreated',
            '.DriverRated',
            '.CustomOrderRequested',
            '.CustomOrderQuoteCreated',
            '.CustomOrderQuoteAccepted',
            '.CustomOrderConverted',
        ],
        enabled,
        onEvent: () => reload(),
    });

    useRealtimeReconnect(reload);
}

export function useAdminCustomOrderEvents(
    only: string[] = ['requests', 'request', 'queue', 'operation'],
): void {
    const reload = useCallback(() => {
        router.reload({
            only,
            preserveUrl: true,
            preserveScroll: true,
        });
    }, [only.join('|')]);

    usePrivateChannelEvents({
        channels: ['admin'],
        events: [
            '.CustomOrderRequested',
            '.CustomOrderQuoteCreated',
            '.CustomOrderQuoteAccepted',
            '.CustomOrderConverted',
            '.CustomOrderUpdated',
        ],
        enabled: true,
        onEvent: () => reload(),
    });

    useRealtimeReconnect(reload);
}

export function useCustomerCustomOrderEvents(customerId?: number | null): void {
    const reload = useCallback(() => {
        router.reload({
            only: ['request', 'requests'],
            preserveUrl: true,
            preserveScroll: true,
        });
    }, []);

    usePrivateChannelEvents({
        channels: [customerId ? `customer.${customerId}` : null],
        events: [
            '.CustomOrderRequested',
            '.CustomOrderQuoteCreated',
            '.CustomOrderQuoteAccepted',
            '.CustomOrderConverted',
            '.CustomOrderUpdated',
        ],
        enabled: Boolean(customerId),
        onEvent: () => reload(),
    });

    useRealtimeReconnect(reload);
}

export function useDriverProfileEvents(driverId?: number | null): void {
    const reload = useCallback(() => {
        router.reload({
            only: ['reputation'],
            preserveUrl: true,
            preserveScroll: true,
        });
    }, []);

    usePrivateChannelEvents({
        channels: [driverId ? `driver.${driverId}` : null],
        events: ['.DriverRated'],
        enabled: Boolean(driverId),
        onEvent: () => reload(),
    });

    useRealtimeReconnect(reload);
}

function useRealtimeReconnect(onReconnect: () => void): void {
    const status = useConnectionStatus();
    const wasConnected = useRef(false);

    useEffect(() => {
        if (status === 'connected') {
            if (wasConnected.current) {
                onReconnect();
            }

            wasConnected.current = true;
        }
    }, [status, onReconnect]);

    useEffect(() => {
        const onFocus = () => onReconnect();
        window.addEventListener('focus', onFocus);

        return () => window.removeEventListener('focus', onFocus);
    }, [onReconnect]);
}
