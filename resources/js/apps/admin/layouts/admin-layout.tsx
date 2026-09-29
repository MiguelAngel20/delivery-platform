import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { adminNavItems } from '@/apps/admin/components/nav-config';
import { DashboardShell } from '@/components/layout/dashboard-shell';
import { PushPermissionPrompt } from '@/components/notifications/push-permission-prompt';
import { PortalInstallAppBanner } from '@/components/pwa/portal-install-app-banner';
import {
    useAdminCustomOrderEvents,
    useAdminOrderEvents,
} from '@/hooks/realtime/use-order-realtime';
import { canAdmin  } from '@/lib/admin-access';
import type {AdminAccess} from '@/lib/admin-access';
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
    const { auth, adminAccess } = usePage().props as {
        auth: Auth;
        adminAccess: AdminAccess | null;
    };
    const navItems = adminNavItems.filter((item) => {
        if (!item.access) {
            return true;
        }

        return canAdmin(adminAccess, item.access);
    });
    const roleLabel =
        auth.user !== null
            ? userRoleLabels[auth.user.role]
            : 'Administrador';

    useAdminOrderEvents(true, [
        'orders',
        'order',
        'queue',
        'operation',
        'incidents',
        'incident',
        'drivers',
        'driver',
        'requests',
        'request',
        'financial',
    ]);
    useAdminCustomOrderEvents([
        'requests',
        'request',
        'queue',
        'operation',
    ]);

    return (
        <>
            <Head>
                <link
                    rel="manifest"
                    href="/admin/manifest.webmanifest"
                    head-key="manifest"
                />
                <meta
                    head-key="apple-mobile-web-app-title"
                    name="apple-mobile-web-app-title"
                    content="ChisDrive Admin"
                />
            </Head>
            <PushPermissionPrompt tone="admin" />
            <PortalInstallAppBanner
                scope="admin"
                appName="ChisDrive Admin"
                body="Gestiona la plataforma desde tu pantalla de inicio."
                ariaLabel="Instalar aplicación de administración"
            />
            <DashboardShell
                homeHref={home()}
                mainNavItems={navItems}
                breadcrumbs={breadcrumbs}
                title={title}
                userRole={roleLabel}
            >
                {children}
            </DashboardShell>
        </>
    );
}
