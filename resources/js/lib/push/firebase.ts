import { initializeApp, type FirebaseApp } from 'firebase/app';
import {
    getMessaging,
    getToken,
    isSupported,
    onMessage,
    type Messaging,
} from 'firebase/messaging';

export type PushWebConfig = {
    apiKey: string;
    authDomain: string;
    projectId: string;
    storageBucket: string;
    messagingSenderId: string;
    appId: string;
};

let app: FirebaseApp | null = null;
let messaging: Messaging | null = null;

function hasConfig(config: PushWebConfig): boolean {
    return Boolean(
        config.apiKey &&
            config.projectId &&
            config.appId &&
            config.messagingSenderId,
    );
}

export async function pushSupported(): Promise<boolean> {
    if (typeof window === 'undefined' || !('Notification' in window)) {
        return false;
    }

    try {
        return await isSupported();
    } catch {
        return false;
    }
}

export async function getFirebaseMessaging(
    config: PushWebConfig,
): Promise<Messaging | null> {
    if (!hasConfig(config)) {
        return null;
    }

    if (!(await pushSupported())) {
        return null;
    }

    if (!app) {
        app = initializeApp(config);
    }

    if (!messaging) {
        messaging = getMessaging(app);
    }

    return messaging;
}

function serviceWorkerUrl(config: PushWebConfig): string {
    const params = new URLSearchParams({
        apiKey: config.apiKey,
        authDomain: config.authDomain,
        projectId: config.projectId,
        storageBucket: config.storageBucket,
        messagingSenderId: config.messagingSenderId,
        appId: config.appId,
    });

    return `/firebase-messaging-sw.js?${params.toString()}`;
}

export async function registerMessagingServiceWorker(
    config: PushWebConfig,
): Promise<ServiceWorkerRegistration | null> {
    if (!('serviceWorker' in navigator) || !hasConfig(config)) {
        return null;
    }

    return navigator.serviceWorker.register(serviceWorkerUrl(config), {
        scope: '/',
    });
}

export async function requestFcmToken(
    config: PushWebConfig,
    vapidKey: string,
): Promise<string | null> {
    if (!vapidKey) {
        return null;
    }

    const registration = await registerMessagingServiceWorker(config);
    const instance = await getFirebaseMessaging(config);

    if (!instance || !registration) {
        return null;
    }

    return getToken(instance, {
        vapidKey,
        serviceWorkerRegistration: registration,
    });
}

/**
 * Foreground FCM handler. Does not show OS notifications when the tab is visible
 * (Reverb already updates the UI). Returns an unsubscribe fn.
 */
export async function listenForegroundMessages(
    config: PushWebConfig,
    onPayload: (payload: {
        title?: string;
        body?: string;
        data?: Record<string, string>;
    }) => void,
): Promise<(() => void) | null> {
    const instance = await getFirebaseMessaging(config);

    if (!instance) {
        return null;
    }

    return onMessage(instance, (payload) => {
        if (document.visibilityState === 'visible') {
            return;
        }

        onPayload({
            title: payload.notification?.title,
            body: payload.notification?.body,
            data: payload.data as Record<string, string> | undefined,
        });
    });
}

const TOKEN_STORAGE_KEY = 'ride_fcm_token';

export function storedFcmToken(): string | null {
    try {
        return localStorage.getItem(TOKEN_STORAGE_KEY);
    } catch {
        return null;
    }
}

export function persistFcmToken(token: string | null): void {
    try {
        if (token) {
            localStorage.setItem(TOKEN_STORAGE_KEY, token);
        } else {
            localStorage.removeItem(TOKEN_STORAGE_KEY);
        }
    } catch {
        // ignore
    }
}
