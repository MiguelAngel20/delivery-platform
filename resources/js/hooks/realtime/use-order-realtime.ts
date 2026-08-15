import { router, usePoll } from '@inertiajs/react';
import { useConnectionStatus } from '@laravel/echo-react';
import { useCallback, useEffect, useRef } from 'react';
import { usePrivateChannelEvents } from '@/hooks/realtime/use-private-channel-events';

const SOUND_KEY = 'ride.business.new_order_sound';
/** Fallback when WebSockets miss events (shared hosting / Pusher hiccups). */
const REALTIME_POLL_MS = 5000;

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

function usePartialReload(only: string[]): () => void {
    const onlyRef = useRef(only);

    useEffect(() => {
        onlyRef.current = only;
    }, [only]);

    return useCallback(() => {
        router.reload({ only: onlyRef.current });
    }, []);
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
    const reload = usePartialReload(only);

    const handleEvent = useCallback(
        (event: string) => {
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
        onEvent: handleEvent,
    });

    useRealtimeSync(reload, only);
}

export function useCustomerOrderEvents(customerId?: number | null, orderId?: number | null): void {
    const only = ['order', 'orders'];
    const reload = usePartialReload(only);

    usePrivateChannelEvents({
        channels: [
            customerId ? `customer.${customerId}` : null,
            orderId ? `order.${orderId}` : null,
        ],
        events: ['.OrderStatusChanged', '.DriverAssigned', '.IncidentCreated', '.CustomOrderQuoteCreated', '.CustomOrderConverted'],
        enabled: Boolean(customerId || orderId),
        onEvent: () => reload(),
    });

    useRealtimeSync(reload, only);
}

export function useDriverOrderEvents(driverId?: number | null, branchIds: number[] = []): void {
    const only = [
        'availableOrders',
        'activeOrders',
        'activeGroups',
        'compatibleOrders',
        'stats',
        'availabilityStatus',
    ];
    const reload = usePartialReload(only);

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

    useRealtimeSync(reload, only);
}

export function useAdminOrderEvents(
    enabled = true,
    only: string[] = ['orders', 'order'],
): void {
    const reload = usePartialReload(only);

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

    useRealtimeSync(reload, only);
}

export function useAdminCustomOrderEvents(
    only: string[] = ['requests', 'request', 'queue', 'operation'],
): void {
    const reload = usePartialReload(only);

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

    useRealtimeSync(reload, only);
}

export function useCustomerCustomOrderEvents(customerId?: number | null): void {
    const only = ['request', 'requests'];
    const reload = usePartialReload(only);

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

    useRealtimeSync(reload, only);
}

export function useDriverProfileEvents(driverId?: number | null): void {
    const only = ['reputation'];
    const reload = usePartialReload(only);

    usePrivateChannelEvents({
        channels: [driverId ? `driver.${driverId}` : null],
        events: ['.DriverRated'],
        enabled: Boolean(driverId),
        onEvent: () => reload(),
    });

    useRealtimeSync(reload, only);
}

/**
 * WebSocket reconnect + focus refresh + poll safety net.
 * Do not pass `preserveUrl: true` to reload — it can prevent props from updating in Inertia.
 * Polling stays on even when Echo is connected so missed/server-side broadcast failures
 * still refresh kitchen/driver/admin lists on shared hosting.
 */
function useRealtimeSync(onReconnect: () => void, only: string[]): void {
    const status = useConnectionStatus();
    const wasConnected = useRef(false);

    usePoll(REALTIME_POLL_MS, () => ({ only }), { keepAlive: true });

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
