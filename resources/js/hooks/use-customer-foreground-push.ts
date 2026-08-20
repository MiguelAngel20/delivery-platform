import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import {
    listenForegroundMessages,
    type PushWebConfig,
} from '@/lib/push/firebase';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
    push?: {
        enabled?: boolean;
        web?: PushWebConfig;
        vapid_key?: string;
    };
};

/**
 * Shows in-app toasts when FCM push arrives while the customer tab is open.
 */
export function useCustomerForegroundPush(): void {
    const { auth, push } = usePage().props as PageProps;

    useEffect(() => {
        if (
            auth.user?.role !== 'customer' ||
            !push?.enabled ||
            !push.web ||
            !push.vapid_key
        ) {
            return;
        }

        let unsubscribe: (() => void) | null = null;
        let cancelled = false;

        void listenForegroundMessages(push.web, (payload) => {
            const clickPath = payload.data?.click_path;

            if (
                clickPath &&
                typeof clickPath === 'string' &&
                window.location.pathname.startsWith('/customer/orders')
            ) {
                router.reload({ only: ['order', 'activeOrders', 'historyOrders'] });
            }
        }).then((fn) => {
            if (cancelled) {
                fn?.();

                return;
            }

            unsubscribe = fn;
        });

        return () => {
            cancelled = true;
            unsubscribe?.();
        };
    }, [auth.user?.id, auth.user?.role, push?.enabled, push?.vapid_key, push?.web]);
}
