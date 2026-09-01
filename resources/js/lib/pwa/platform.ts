export type InstallPlatform = 'android' | 'ios' | 'desktop' | 'unsupported';

const DISMISS_STORAGE_KEY = 'ride.pwa.install-banner.dismissed-at';

/** Tiempo antes de volver a mostrar el banner tras cerrarlo sin instalar. */
export const INSTALL_BANNER_REMINDER_MS = 3 * 60 * 1000;

export const INSTALL_BANNER_INITIAL_DELAY_MS = 1200;

export function isStandaloneApp(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    const navigatorWithStandalone = window.navigator as Navigator & {
        standalone?: boolean;
    };

    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        navigatorWithStandalone.standalone === true
    );
}

export function isMobileUserAgent(): boolean {
    if (typeof navigator === 'undefined') {
        return false;
    }

    return /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);
}

export function isIosDevice(): boolean {
    if (typeof navigator === 'undefined') {
        return false;
    }

    const ua = navigator.userAgent;

    return (
        /iPad|iPhone|iPod/.test(ua) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
    );
}

export function isAndroidDevice(): boolean {
    if (typeof navigator === 'undefined') {
        return false;
    }

    return /Android/i.test(navigator.userAgent);
}

export function detectInstallPlatform(): InstallPlatform {
    if (isIosDevice()) {
        return 'ios';
    }

    if (isAndroidDevice()) {
        return 'android';
    }

    if (!isMobileUserAgent()) {
        return 'desktop';
    }

    return 'unsupported';
}

export function isInstallBannerDismissed(): boolean {
    return getInstallBannerReminderRemainingMs() > 0;
}

export function getInstallBannerReminderRemainingMs(): number {
    try {
        const raw = localStorage.getItem(DISMISS_STORAGE_KEY);

        if (!raw) {
            return 0;
        }

        const dismissedAt = Number.parseInt(raw, 10);

        if (Number.isNaN(dismissedAt)) {
            return 0;
        }

        const remaining =
            INSTALL_BANNER_REMINDER_MS - (Date.now() - dismissedAt);

        return remaining > 0 ? remaining : 0;
    } catch {
        return 0;
    }
}

export function dismissInstallBanner(): void {
    try {
        localStorage.setItem(DISMISS_STORAGE_KEY, String(Date.now()));
    } catch {
        // ignore
    }
}

export async function registerPwaServiceWorker(): Promise<ServiceWorkerRegistration | null> {
    if (!('serviceWorker' in navigator)) {
        return null;
    }

    try {
        const existing = await navigator.serviceWorker.getRegistration('/');

        if (existing) {
            return existing;
        }

        return await navigator.serviceWorker.register('/sw.js', {
            scope: '/',
        });
    } catch {
        return null;
    }
}
