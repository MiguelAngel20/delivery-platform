export const SHARED_SERVICE_WORKER_SCOPE = '/';

export const SHARED_SERVICE_WORKER_SCRIPT = '/sw.js';

export type FirebaseWebWorkerConfig = {
    apiKey: string;
    authDomain: string;
    projectId: string;
    storageBucket: string;
    messagingSenderId: string;
    appId: string;
};

export function hasFirebaseWebWorkerConfig(
    config: FirebaseWebWorkerConfig | null | undefined,
): config is FirebaseWebWorkerConfig {
    return Boolean(
        config?.apiKey &&
            config.projectId &&
            config.appId &&
            config.messagingSenderId,
    );
}

export function messagingServiceWorkerUrl(
    config: FirebaseWebWorkerConfig,
): string {
    const params = new URLSearchParams({
        apiKey: config.apiKey,
        authDomain: config.authDomain,
        projectId: config.projectId,
        storageBucket: config.storageBucket,
        messagingSenderId: config.messagingSenderId,
        appId: config.appId,
    });

    return `${SHARED_SERVICE_WORKER_SCRIPT}?${params.toString()}`;
}

export async function registerSharedServiceWorker(
    config: FirebaseWebWorkerConfig | null | undefined,
): Promise<ServiceWorkerRegistration | null> {
    if (
        typeof navigator === 'undefined' ||
        !('serviceWorker' in navigator) ||
        !hasFirebaseWebWorkerConfig(config)
    ) {
        return null;
    }

    try {
        return await navigator.serviceWorker.register(
            messagingServiceWorkerUrl(config),
            { scope: SHARED_SERVICE_WORKER_SCOPE },
        );
    } catch {
        return null;
    }
}
