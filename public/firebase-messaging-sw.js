/* Firebase Cloud Messaging service worker (background / closed tab).
 * Public web config is passed as query params when registering — never private keys.
 */
/* eslint-disable no-undef */
importScripts(
    'https://www.gstatic.com/firebasejs/12.17.1/firebase-app-compat.js',
);
importScripts(
    'https://www.gstatic.com/firebasejs/12.17.1/firebase-messaging-compat.js',
);

const params = new URL(self.location.href).searchParams;

const firebaseConfig = {
    apiKey: params.get('apiKey') || '',
    authDomain: params.get('authDomain') || '',
    projectId: params.get('projectId') || '',
    storageBucket: params.get('storageBucket') || '',
    messagingSenderId: params.get('messagingSenderId') || '',
    appId: params.get('appId') || '',
};

if (firebaseConfig.apiKey && firebaseConfig.projectId && firebaseConfig.appId) {
    firebase.initializeApp(firebaseConfig);

    const messaging = firebase.messaging();

    messaging.onBackgroundMessage((payload) => {
        const title =
            payload.notification?.title ||
            payload.data?.title ||
            'RIDE';
        const body =
            payload.notification?.body ||
            payload.data?.body ||
            '';
        const clickPath = payload.data?.click_path || '/';

        self.registration.showNotification(title, {
            body,
            data: {
                click_path: clickPath,
                target_type: payload.data?.target_type || '',
                target_id: payload.data?.target_id || '',
            },
            icon: '/assets/branding/app-icon.svg',
        });
    });
}

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const clickPath = event.notification?.data?.click_path;
    const path =
        typeof clickPath === 'string' &&
        clickPath.startsWith('/') &&
        !clickPath.startsWith('//')
            ? clickPath
            : '/';

    event.waitUntil(
        clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((windowClients) => {
                for (const client of windowClients) {
                    if ('focus' in client) {
                        client.focus();
                        if ('navigate' in client) {
                            return client.navigate(path);
                        }
                        return client;
                    }
                }

                if (clients.openWindow) {
                    return clients.openWindow(path);
                }

                return undefined;
            }),
    );
});
