import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { BranchSelector } from '@/apps/business/components/branch-selector';
import {
    businessPortalRoleLabels,
    getBusinessNavItems,
} from '@/apps/business/components/nav-config';
import { DashboardShell } from '@/components/layout/dashboard-shell';
import { PushPermissionPrompt } from '@/components/notifications/push-permission-prompt';
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
            <PushPermissionPrompt tone="business" />
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
