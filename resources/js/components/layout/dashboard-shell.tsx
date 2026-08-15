import { useEffect } from 'react';
import type { ReactNode } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppTopbar } from '@/components/navigation/app-topbar';
import { NotificationBell } from '@/components/notifications/notification-bell';
import { forceLightTheme } from '@/hooks/use-appearance';
import type { BreadcrumbItem, NavItem } from '@/types';

type DashboardShellProps = {
    children: ReactNode;
    title?: string;
    breadcrumbs?: BreadcrumbItem[];
    homeHref: NavItem['href'];
    mainNavItems: NavItem[];
    footerNavItems?: NavItem[];
    navLabel?: string;
    topbarActions?: ReactNode;
    userRole?: string;
};

export function DashboardShell({
    children,
    title,
    breadcrumbs = [],
    homeHref,
    mainNavItems,
    footerNavItems = [],
    navLabel,
    topbarActions,
    userRole,
}: DashboardShellProps) {
    useEffect(() => forceLightTheme(), []);

    return (
        <AppShell variant="sidebar">
            <AppSidebar
                homeHref={homeHref}
                mainNavItems={mainNavItems}
                footerNavItems={footerNavItems}
                navLabel={navLabel}
                userRole={userRole}
            />
            <AppContent
                variant="sidebar"
                className="min-h-svh overflow-x-hidden bg-[#F8FAFC] text-[#0F172A]"
            >
                <AppTopbar
                    title={title}
                    breadcrumbs={breadcrumbs}
                    actions={topbarActions}
                    userRole={userRole}
                    notifications={<NotificationBell />}
                />
                {children}
            </AppContent>
        </AppShell>
    );
}
