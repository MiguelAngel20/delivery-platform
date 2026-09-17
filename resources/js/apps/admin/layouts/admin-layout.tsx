import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { adminNavItems } from '@/apps/admin/components/nav-config';
import { DashboardShell } from '@/components/layout/dashboard-shell';
import { PushPermissionPrompt } from '@/components/notifications/push-permission-prompt';
import { PortalInstallAppBanner } from '@/components/pwa/portal-install-app-banner';
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
