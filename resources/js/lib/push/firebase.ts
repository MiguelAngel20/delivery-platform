import { initializeApp  } from 'firebase/app';
import type {FirebaseApp} from 'firebase/app';
import {
    getMessaging,
    getToken,
    isSupported,
    onMessage
    
} from 'firebase/messaging';
import type {Messaging} from 'firebase/messaging';
import { showBrowserNotification } from '@/lib/push/browser-notification';
import { registerSharedServiceWorker } from '@/lib/push/service-worker';

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

export function getFirebaseApp(config: PushWebConfig): FirebaseApp | null {
    if (!hasConfig(config)) {
        return null;
    }

    if (!app) {
        app = initializeApp(config);
    }

    return app;
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

    const firebaseApp = getFirebaseApp(config);

    if (!firebaseApp) {
        return null;
    }

    if (!messaging) {
        messaging = getMessaging(firebaseApp);
    }

    return messaging;
}

export async function registerMessagingServiceWorker(
    config: PushWebConfig,
): Promise<ServiceWorkerRegistration | null> {
    return registerSharedServiceWorker(config);
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
 * Foreground FCM handler. Returns an unsubscribe fn.
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
        const title =
            payload.notification?.title || payload.data?.title || undefined;
        const body =
            payload.notification?.body || payload.data?.body || undefined;

        if (title) {
            void showBrowserNotification(title, body);
        }

        onPayload({
            title,
            body,
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
