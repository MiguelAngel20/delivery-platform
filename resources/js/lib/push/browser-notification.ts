export async function showBrowserNotification(
    title: string,
    body?: string,
    tag?: string | null,
): Promise<void> {
    if (typeof window === 'undefined' || !('Notification' in window)) {
        return;
    }

    if (Notification.permission !== 'granted') {
        return;
    }

    const options: NotificationOptions = {
        body: body || '',
        icon: '/assets/branding/app-icon.svg',
        tag: tag && tag !== '' ? tag : `ride:${title}`,
        renotify: Boolean(tag),
    };

    try {
        const registration = await navigator.serviceWorker?.ready;

        if (registration) {
            await registration.showNotification(title, options);

            return;
        }
    } catch {
        // Fall through to the page Notification API.
    }

    try {
        new Notification(title, options);
    } catch {
        // Chrome often blocks this without a user gesture.
    }
}
