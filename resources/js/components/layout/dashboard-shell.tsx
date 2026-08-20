import type { ReactNode } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppTopbar } from '@/components/navigation/app-topbar';
import { NotificationBell } from '@/components/notifications/notification-bell';
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
    return (
        <AppShell variant="sidebar">
            <AppSidebar
                homeHref={homeHref}
                mainNavItems={mainNavItems}
                footerNavItems={footerNavItems}
                navLabel={navLabel}
            />
            <AppContent
                variant="sidebar"
                className="min-h-svh overflow-x-hidden bg-background text-foreground"
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
