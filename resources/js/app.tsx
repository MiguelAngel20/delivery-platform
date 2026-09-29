import { createInertiaApp } from '@inertiajs/react';
import { configureEcho } from '@laravel/echo-react';
import type { ComponentType, ReactNode } from 'react';
import AdminLayout from '@/apps/admin/layouts/admin-layout';
import BusinessLayout from '@/apps/business/layouts/business-layout';
import CustomerLayout from '@/apps/customer/layouts/customer-layout';
import DriverLayout from '@/apps/driver/layouts/driver-layout';
import PublicLayout from '@/apps/public/layouts/public-layout';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import MapsLayout from '@/layouts/maps-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { installMetaPixel } from '@/lib/meta-pixel';

const broadcastConnection =
    import.meta.env.VITE_BROADCAST_CONNECTION || 'reverb';

function envValue(value: string | undefined): string | undefined {
    const trimmed = value?.trim();

    return trimmed ? trimmed : undefined;
}

if (broadcastConnection === 'pusher') {
    const pusherHost = envValue(import.meta.env.VITE_PUSHER_HOST);
    const pusherPort = envValue(import.meta.env.VITE_PUSHER_PORT);

    configureEcho({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
        forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') !== 'http',
        // An empty VITE_PUSHER_HOST must not override the cluster host.
        wsHost: pusherHost,
        wsPort: pusherHost && pusherPort ? Number(pusherPort) : undefined,
        wssPort: pusherHost && pusherPort ? Number(pusherPort) : undefined,
    });
} else {
    // Local / VPS: Laravel Reverb (requires `php artisan reverb:start`)
    const reverbPort = Number(envValue(import.meta.env.VITE_REVERB_PORT) ?? 8080);

    configureEcho({
        broadcaster: 'reverb',
        key: envValue(import.meta.env.VITE_REVERB_APP_KEY),
        wsHost: envValue(import.meta.env.VITE_REVERB_HOST) ?? '127.0.0.1',
        wsPort: reverbPort,
        wssPort: reverbPort,
        forceTLS: (envValue(import.meta.env.VITE_REVERB_SCHEME) ?? 'http') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}

const appName = import.meta.env.VITE_APP_NAME || 'ChisDrive';

installMetaPixel();

type LayoutComponent = ComponentType<{ children: ReactNode }>;

function resolvePortalLayout(name: string): LayoutComponent | LayoutComponent[] {
    switch (true) {
        case name.startsWith('public/'):
            return PublicLayout;
        case name.startsWith('customer/'):
            return CustomerLayout;
        case name.startsWith('business/'):
            return BusinessLayout;
        case name.startsWith('driver/'):
            return DriverLayout;
        case name.startsWith('admin/'):
            return AdminLayout;
        case name.startsWith('auth/'):
            return AuthLayout;
        case name.startsWith('settings/'):
            return [AppLayout, SettingsLayout];
        default:
            return AppLayout;
    }
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        const portal = resolvePortalLayout(name);
        const nested = Array.isArray(portal) ? portal : [portal];

        return [MapsLayout, ...nested];
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#FF7A00',
    },
});

initializeTheme();
