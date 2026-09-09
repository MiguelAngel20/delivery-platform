import { Head } from '@inertiajs/react';
import { useEffect } from 'react';
import type { ReactNode } from 'react';
import { DriverAvailabilityControl } from '@/apps/driver/components/driver-availability-control';
import { DriverInstallAppBanner } from '@/apps/driver/components/driver-install-app-banner';
import { driverNavItems } from '@/apps/driver/components/nav-config';
import { MobileShell } from '@/components/layout/mobile-shell';
import { NotificationBell } from '@/components/notifications/notification-bell';
import { PushPermissionPrompt } from '@/components/notifications/push-permission-prompt';
import { forceLightTheme } from '@/hooks/use-appearance';
import { home } from '@/routes/driver';

export default function DriverLayout({ children }: { children: ReactNode }) {
    useEffect(() => forceLightTheme(), []);

    return (
        <>
            <Head>
                <link
                    rel="manifest"
                    href="/driver/manifest.webmanifest"
                    head-key="manifest"
                />
                <meta
                    head-key="apple-mobile-web-app-title"
                    name="apple-mobile-web-app-title"
                    content="ChisDrive Repartidor"
                />
            </Head>
            <PushPermissionPrompt tone="driver" />
            <DriverInstallAppBanner />
            <MobileShell
                homeHref={home()}
                navItems={driverNavItems}
                persistBottomNav
                topbarEnd={
                    <div className="flex items-center gap-1">
                        <NotificationBell compact />
                        <DriverAvailabilityControl compact />
                    </div>
                }
                className="bg-background"
            >
                {children}
            </MobileShell>
        </>
    );
}
