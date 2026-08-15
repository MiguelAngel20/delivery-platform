import { echo, echoIsConfigured } from '@laravel/echo-react';
import { useEffect, useRef } from 'react';
import {
    ORDER_REALTIME_EVENTS,
    type OrderRealtimePayload,
} from '@/lib/realtime/types';

type Options = {
    channels: Array<string | null | undefined>;
    events?: readonly string[];
    enabled?: boolean;
    onEvent: (event: string, payload: OrderRealtimePayload) => void;
};

/**
 * Subscribe to multiple private channels with shared event handlers.
 * Leaves channels on cleanup. Safe when Echo is not configured.
 */
export function usePrivateChannelEvents({
    channels,
    events = ORDER_REALTIME_EVENTS,
    enabled = true,
    onEvent,
}: Options): void {
    const handlerRef = useRef(onEvent);
    handlerRef.current = onEvent;

    const channelKey = channels.filter(Boolean).join('|');
    const eventKey = events.join('|');

    useEffect(() => {
        if (!enabled || !echoIsConfigured() || channelKey === '') {
            return;
        }

        const instance = echo();
        const uniqueChannels = [...new Set(channelKey.split('|').filter(Boolean))];
        const eventNames = eventKey.split('|').filter(Boolean);

        for (const name of uniqueChannels) {
            const channel = instance.private(name);

            for (const eventName of eventNames) {
                channel.listen(eventName, (payload: OrderRealtimePayload) => {
                    if (import.meta.env.DEV) {
                        console.debug('[realtime]', name, eventName, payload.order_number);
                    }

                    handlerRef.current(eventName, payload);
                });
            }
        }

        return () => {
            for (const name of uniqueChannels) {
                instance.leave(name);
            }
        };
    }, [channelKey, eventKey, enabled]);
}
