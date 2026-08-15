import { useEffect } from 'react';
import type { ReactNode } from 'react';
import { DriverAvailabilityControl } from '@/apps/driver/components/driver-availability-control';
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
            <PushPermissionPrompt tone="driver" />
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
