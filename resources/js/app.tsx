import { createInertiaApp } from '@inertiajs/react';
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
import { configureEcho } from '@laravel/echo-react';

configureEcho({
    broadcaster: 'reverb',
});

const appName = import.meta.env.VITE_APP_NAME || 'RIDE';

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
