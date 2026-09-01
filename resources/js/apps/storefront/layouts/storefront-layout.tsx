import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import type { ReactNode } from 'react';
import {
    consumeCheckoutIntent,
    consumePendingCartClear,
} from '@/apps/storefront/cart/use-storefront-cart';
import { StorefrontBottomNav } from '@/apps/storefront/components/storefront-bottom-nav';
import { StorefrontFooter } from '@/apps/storefront/components/storefront-footer';
import { StorefrontHeader } from '@/apps/storefront/components/storefront-header';
import { useStorefrontShell } from '@/apps/storefront/hooks/use-storefront-shell';
import { PushPermissionPrompt } from '@/components/notifications/push-permission-prompt';
import { useCustomerForegroundPush } from '@/hooks/use-customer-foreground-push';
import { forceLightTheme } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import type { Auth } from '@/types';

export default function StorefrontLayout({
    children,
}: {
    children: ReactNode;
}) {
    const { auth } = usePage().props as { auth: Auth };
    const { showBottomNav } = useStorefrontShell();

    useCustomerForegroundPush();

    useEffect(() => forceLightTheme(), []);

    useEffect(() => {
        consumePendingCartClear();
    }, []);

    useEffect(() => {
        if (auth.user?.role !== 'customer') {
            return;
        }

        const intent = consumeCheckoutIntent();

        if (intent) {
            router.visit(intent);
        }
    }, [auth.user?.role]);

    return (
        <div className="flex min-h-screen min-w-0 flex-col overflow-x-clip bg-background text-foreground">
            {auth.user?.role === 'customer' ? (
                <PushPermissionPrompt tone="customer" />
            ) : null}
            <StorefrontHeader />
            <main className="mx-auto w-full min-w-0 max-w-6xl flex-1">
                {children}
            </main>
            <div className={cn('md:pb-0', showBottomNav ? 'pb-24' : 'pb-0')}>
                <StorefrontFooter className="hidden md:block" />
            </div>
            {showBottomNav ? <StorefrontBottomNav /> : null}
        </div>
    );
}
