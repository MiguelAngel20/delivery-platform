import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { BranchSelector } from '@/apps/business/components/branch-selector';
import {
    businessPortalRoleLabels,
    getBusinessNavItems,
} from '@/apps/business/components/nav-config';
import { DashboardShell } from '@/components/layout/dashboard-shell';
import { PushPermissionPrompt } from '@/components/notifications/push-permission-prompt';
import { PortalInstallAppBanner } from '@/components/pwa/portal-install-app-banner';
import { home } from '@/routes/business';
import type { Auth, BreadcrumbItem } from '@/types';
import type { BusinessContext } from '@/types/business';

export default function BusinessLayout({
    children,
    breadcrumbs = [],
    title,
}: {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    title?: string;
}) {
    const { auth, businessContext } = usePage().props as {
        auth: Auth;
        businessContext: BusinessContext | null;
    };
    const role = auth.user?.role;
    const roleLabel =
        role !== undefined
            ? (businessPortalRoleLabels[role] ?? 'Negocio')
            : 'Negocio';
    const businessLabel = businessContext?.business.name
        ? `${businessContext.business.name} · ${roleLabel}`
        : roleLabel;

    return (
        <>
            <Head>
                <link
                    rel="manifest"
                    href="/business/manifest.webmanifest"
                    head-key="manifest"
                />
                <meta
                    head-key="apple-mobile-web-app-title"
                    name="apple-mobile-web-app-title"
                    content="ChisDrive Negocio"
                />
            </Head>
            <PushPermissionPrompt tone="business" />
            <PortalInstallAppBanner
                scope="business"
                appName="ChisDrive Negocio"
                body="Gestiona pedidos y catálogo desde tu pantalla de inicio."
                ariaLabel="Instalar aplicación de negocio"
            />
            <DashboardShell
                homeHref={home()}
                mainNavItems={getBusinessNavItems(role)}
                breadcrumbs={breadcrumbs}
                title={title}
                userRole={businessLabel}
                topbarActions={<BranchSelector />}
            >
                {children}
            </DashboardShell>
        </>
    );
}
