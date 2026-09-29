import { echo, echoIsConfigured } from '@laravel/echo-react';

type EventHandler = (payload: unknown) => void;

const subscriberCounts = new Map<string, number>();
const leaveTimers = new Map<string, number>();

/**
 * Keep a private channel subscribed while at least one screen is listening.
 * Leaving is delayed so React Strict Mode (setup → cleanup → setup) does not
 * drop the WebSocket subscription before the next listener attaches.
 */
export function retainPrivateChannel(name: string): void {
    const pendingLeave = leaveTimers.get(name);

    if (pendingLeave !== undefined) {
        window.clearTimeout(pendingLeave);
        leaveTimers.delete(name);
    }

    subscriberCounts.set(name, (subscriberCounts.get(name) ?? 0) + 1);
}

export function releasePrivateChannel(name: string): void {
    const remaining = (subscriberCounts.get(name) ?? 1) - 1;

    if (remaining > 0) {
        subscriberCounts.set(name, remaining);

        return;
    }

    subscriberCounts.delete(name);

    const timer = window.setTimeout(() => {
        leaveTimers.delete(name);

        if ((subscriberCounts.get(name) ?? 0) > 0 || !echoIsConfigured()) {
            return;
        }

        echo().leave(name);
    }, 150);

    leaveTimers.set(name, timer);
}

export function listenOnPrivateChannel(
    name: string,
    events: readonly string[],
    onEvent: (eventName: string, payload: unknown) => void,
): () => void {
    if (!echoIsConfigured()) {
        return () => {};
    }

    retainPrivateChannel(name);

    const channel = echo().private(name);
    const bindings = events.map((eventName) => {
        const handler: EventHandler = (payload) => {
            onEvent(eventName, payload);
        };

        channel.listen(eventName, handler);

        return { eventName, handler };
    });

    return () => {
        for (const binding of bindings) {
            channel.stopListening(binding.eventName, binding.handler);
        }

        releasePrivateChannel(name);
    };
}
