import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import type { ReactNode } from 'react';
import { consumeCheckoutIntent } from '@/apps/storefront/cart/use-storefront-cart';
import { StorefrontBottomNav } from '@/apps/storefront/components/storefront-bottom-nav';
import { StorefrontHeader } from '@/apps/storefront/components/storefront-header';
import { PushPermissionPrompt } from '@/components/notifications/push-permission-prompt';
import { forceLightTheme } from '@/hooks/use-appearance';
import type { Auth } from '@/types';

export default function StorefrontLayout({
    children,
}: {
    children: ReactNode;
}) {
    const { auth } = usePage().props as { auth: Auth };

    useEffect(() => forceLightTheme(), []);

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
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            {auth.user?.role === 'customer' ? (
                <PushPermissionPrompt tone="customer" />
            ) : null}
            <StorefrontHeader />
            <main className="mx-auto w-full max-w-6xl flex-1 pb-24 md:pb-8">
                {children}
            </main>
            <StorefrontBottomNav />
        </div>
    );
}
