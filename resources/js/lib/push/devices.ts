import {
    persistFcmToken,
    requestFcmToken,
    storedFcmToken,
    type PushWebConfig,
} from '@/lib/push/firebase';

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function jsonFetch(
    url: string,
    options: RequestInit = {},
): Promise<Response> {
    return fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
            ...(options.headers ?? {}),
        },
        ...options,
    });
}

export async function registerPushDevice(payload: {
    token: string;
    device_type?: string;
    browser?: string;
    platform?: string;
    device_name?: string;
}): Promise<boolean> {
    const response = await jsonFetch('/push/devices', {
        method: 'POST',
        body: JSON.stringify(payload),
    });

    if (response.ok) {
        persistFcmToken(payload.token);
    }

    return response.ok;
}

export async function deactivatePushDevice(token: string): Promise<void> {
    await jsonFetch('/push/devices', {
        method: 'DELETE',
        body: JSON.stringify({ token }),
        headers: {
            'X-Push-Token': token,
        },
    });
    persistFcmToken(null);
}

export async function enablePushForCurrentUser(options: {
    web: PushWebConfig;
    vapidKey: string;
}): Promise<'granted' | 'denied' | 'unsupported' | 'error'> {
    if (!('Notification' in window)) {
        return 'unsupported';
    }

    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
        return permission === 'denied' ? 'denied' : 'error';
    }

    try {
        const token = await requestFcmToken(options.web, options.vapidKey);

        if (!token) {
            return 'error';
        }

        const ok = await registerPushDevice({
            token,
            device_type: 'web',
            browser: navigator.userAgent.includes('Firefox')
                ? 'Firefox'
                : navigator.userAgent.includes('Edg')
                  ? 'Edge'
                  : navigator.userAgent.includes('Chrome')
                    ? 'Chrome'
                    : 'Web',
            platform: navigator.platform || undefined,
            device_name: 'Web',
        });

        return ok ? 'granted' : 'error';
    } catch {
        return 'error';
    }
}

export async function syncGrantedPushSubscription(options: {
    web: PushWebConfig;
    vapidKey: string;
}): Promise<void> {
    if (typeof window === 'undefined' || !('Notification' in window)) {
        return;
    }

    if (Notification.permission !== 'granted') {
        return;
    }

    try {
        const token = await requestFcmToken(options.web, options.vapidKey);

        if (!token) {
            return;
        }

        await registerPushDevice({
            token,
            device_type: 'web',
            browser: navigator.userAgent.includes('Firefox')
                ? 'Firefox'
                : navigator.userAgent.includes('Edg')
                  ? 'Edge'
                  : navigator.userAgent.includes('Chrome')
                    ? 'Chrome'
                    : 'Web',
            platform: navigator.platform || undefined,
            device_name: 'Web',
        });
    } catch {
        // Token refresh is best-effort; in-app notifications still work.
    }
}

export async function deactivateStoredPushDevice(): Promise<void> {
    const token = storedFcmToken();

    if (!token) {
        return;
    }

    await deactivatePushDevice(token);
}
