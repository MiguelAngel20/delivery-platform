import { router } from '@inertiajs/react';

/**
 * Browser Meta Pixel for the public site and the customer flow.
 *
 * Purchase and the Laravel Conversions API are intentionally not implemented.
 * When they are, send the same event from the browser and from Laravel with one
 * shared event id. Meta matches them with the browser option `{ eventID }`.
 * Pass that id as the third argument of trackMetaEvent(); do not invent a
 * second identifier later.
 */

type MetaPixelParams = Record<string, string | number | boolean>;

type FbqCommand = {
    (...args: unknown[]): void;
    callMethod?: (...args: unknown[]) => void;
    queue: unknown[][];
    loaded: boolean;
    version: string;
    push: FbqCommand;
};

declare global {
    interface Window {
        fbq?: FbqCommand;
        _fbq?: FbqCommand;
    }
}

const PIXEL_SCRIPT_SRC = 'https://connect.facebook.net/en_US/fbevents.js';
const PIXEL_SCRIPT_MARKER = 'data-meta-pixel';

const BLOCKED_PARAM_KEYS = [
    'email',
    'em',
    'phone',
    'ph',
    'fn',
    'ln',
    'name',
    'first_name',
    'last_name',
    'address',
    'city',
    'zip',
    'latitude',
    'longitude',
    'lat',
    'lng',
    'token',
    'password',
    'rfc',
    'notes',
];

let bootstrapped = false;
const trackedVisitIds = new Set<string>();

function pixelId(): string {
    return (import.meta.env.VITE_META_PIXEL_ID ?? '').trim();
}

/**
 * Production builds send events when a Pixel ID exists.
 * `vite dev` does not, unless VITE_META_PIXEL_DEBUG=true is set on purpose.
 */
export function isMetaPixelEnabled(): boolean {
    if (pixelId() === '') {
        return false;
    }

    if (import.meta.env.VITE_META_PIXEL_DEBUG === 'true') {
        return true;
    }

    return import.meta.env.PROD;
}

function isCustomerFacingPage(component: string): boolean {
    if (component.startsWith('public/') || component.startsWith('customer/')) {
        return true;
    }

    return component.startsWith('auth/') && !component.includes('driver');
}

function installStub(): void {
    if (typeof window === 'undefined' || window.fbq) {
        return;
    }

    const fbq = Object.assign(
        (...args: unknown[]): void => {
            const command = fbq as FbqCommand;

            if (command.callMethod) {
                command.callMethod(...args);

                return;
            }

            command.queue.push(args);
        },
        {
            queue: [] as unknown[][],
            loaded: true,
            version: '2.0',
        },
    ) as FbqCommand;

    fbq.push = fbq;
    window.fbq = fbq;

    if (!window._fbq) {
        window._fbq = fbq;
    }
}

function installScript(): void {
    if (typeof document === 'undefined') {
        return;
    }

    if (document.querySelector(`script[${PIXEL_SCRIPT_MARKER}]`)) {
        return;
    }

    const script = document.createElement('script');
    script.async = true;
    script.src = PIXEL_SCRIPT_SRC;
    script.setAttribute(PIXEL_SCRIPT_MARKER, 'true');

    const firstScript = document.getElementsByTagName('script')[0];
    firstScript?.parentNode?.insertBefore(script, firstScript);
}

function sanitizeParams(
    params: MetaPixelParams | undefined,
): MetaPixelParams | undefined {
    if (!params) {
        return undefined;
    }

    const safe: MetaPixelParams = {};

    for (const [key, value] of Object.entries(params)) {
        if (BLOCKED_PARAM_KEYS.includes(key.toLowerCase())) {
            continue;
        }

        safe[key] = value;
    }

    return safe;
}

function callFbq(
    command: 'track' | 'trackCustom',
    eventName: string,
    params?: MetaPixelParams,
    eventId?: string,
): void {
    if (!isMetaPixelEnabled() || typeof window === 'undefined' || !window.fbq) {
        return;
    }

    const safeParams = sanitizeParams(params) ?? {};
    const options = eventId ? { eventID: eventId } : undefined;

    window.fbq(command, eventName, safeParams, options);
}

export function trackMetaEvent(
    eventName: string,
    params?: MetaPixelParams,
    eventId?: string,
): void {
    callFbq('track', eventName, params, eventId);
}

export function trackMetaCustomEvent(
    eventName: string,
    params?: MetaPixelParams,
    eventId?: string,
): void {
    callFbq('trackCustom', eventName, params, eventId);
}

function trackPageView(component: string, visitId?: string): void {
    if (!isCustomerFacingPage(component)) {
        return;
    }

    if (visitId) {
        if (trackedVisitIds.has(visitId)) {
            return;
        }

        trackedVisitIds.add(visitId);
    }

    trackMetaEvent('PageView');
}

/**
 * Loads the Pixel once and records PageView on the initial document and on
 * later Inertia visits. Inertia 3 emits `navigate` for both.
 *
 * The official <noscript> image is omitted: this SPA does not render without
 * JavaScript, so that fallback would only count unusable visits.
 */
export function installMetaPixel(): void {
    if (bootstrapped || !isMetaPixelEnabled()) {
        return;
    }

    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    bootstrapped = true;
    installStub();
    installScript();
    window.fbq?.('init', pixelId());

    router.on('navigate', (event) => {
        const detail = (
            event as CustomEvent<{
                page?: { component?: string };
                visitId?: string;
            }>
        ).detail;

        const component = detail?.page?.component;

        if (!component) {
            return;
        }

        trackPageView(component, detail.visitId);
    });
}
