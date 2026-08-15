import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { adminNavItems } from '@/apps/admin/components/nav-config';
import { DashboardShell } from '@/components/layout/dashboard-shell';
import { PushPermissionPrompt } from '@/components/notifications/push-permission-prompt';
import { home } from '@/routes/admin';
import type { Auth, BreadcrumbItem } from '@/types';
import { userRoleLabels } from '@/types/auth';

export default function AdminLayout({
    children,
    breadcrumbs = [],
    title,
}: {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    title?: string;
}) {
    const { auth } = usePage().props as { auth: Auth };
    const roleLabel =
        auth.user !== null
            ? userRoleLabels[auth.user.role]
            : 'Administrador';

    return (
        <>
            <PushPermissionPrompt tone="admin" />
            <DashboardShell
                homeHref={home()}
                mainNavItems={adminNavItems}
                breadcrumbs={breadcrumbs}
                title={title}
                userRole={roleLabel}
            >
                {children}
            </DashboardShell>
        </>
    );
}
