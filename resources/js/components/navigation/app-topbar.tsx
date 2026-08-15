import type { ReactNode } from 'react';
import { UserMenu } from '@/components/navigation/user-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type AppTopbarProps = {
    title?: string;
    breadcrumbs?: BreadcrumbItem[];
    actions?: ReactNode;
    className?: string;
    showSidebarTrigger?: boolean;
    showNotifications?: boolean;
    notifications?: ReactNode;
    userRole?: string;
};

export function AppTopbar({
    title,
    breadcrumbs = [],
    actions,
    className,
    showSidebarTrigger = true,
    showNotifications = true,
    notifications,
    userRole,
}: AppTopbarProps) {
    const pageTitle =
        title ?? breadcrumbs[breadcrumbs.length - 1]?.title ?? '';

    return (
        <header
            className={cn(
                'sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between gap-3 border-b border-[#E2E8F0] bg-white px-4 md:px-6',
                className,
            )}
        >
            <div className="flex min-w-0 items-center gap-2">
                {showSidebarTrigger ? (
                    <SidebarTrigger className="-ml-1 text-navy" />
                ) : null}
                {pageTitle ? (
                    <h1 className="truncate text-base font-semibold text-navy">
                        {pageTitle}
                    </h1>
                ) : null}
            </div>

            <div className="flex items-center gap-1 sm:gap-2">
                {actions}
                {showNotifications ? notifications : null}
                <UserMenu role={userRole} />
            </div>
        </header>
    );
}
