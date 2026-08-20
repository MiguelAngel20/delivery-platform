import { Head, Link } from '@inertiajs/react';
import type { BusinessBranchDetail } from '@/apps/business/components/business-branch-form';
import { StatusBadge } from '@/components/data-display/status-badge';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { BackButton } from '@/components/navigation/back-button';
import business from '@/routes/business';
import { edit } from '@/routes/business/settings/branches';

type Props = {
    branches: BusinessBranchDetail[];
};

export default function BusinessSettingsBranchesIndex({ branches }: Props) {
    return (
        <>
            <Head title="Sucursales" />
            <PageContainer>
                <PageHeader
                    title="Sucursales"
                    description="Edita la información de tus sucursales."
                    actions={
                        <BackButton href={business.settings.index.url()} />
                    }
                />
                <ContentCard>
                    {branches.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No tienes sucursales asignadas para editar.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border border-[#E2E8F0]">
                            <table className="min-w-full text-sm">
                                <thead className="bg-[#F8FAFC] text-left text-[#64748B]">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">
                                            Sucursal
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Dirección
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Horario hoy
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Estado
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {branches.map((branch) => (
                                        <tr
                                            key={branch.id}
                                            className="border-t border-[#E2E8F0]"
                                        >
                                            <td className="px-4 py-3 font-medium text-navy">
                                                {branch.name}
                                            </td>
                                            <td className="px-4 py-3 text-[#475569]">
                                                {branch.address_text}
                                            </td>
                                            <td className="px-4 py-3 text-[#475569]">
                                                {branch.schedule_label ?? '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge tone="neutral">
                                                    {branch.status_label}
                                                </StatusBadge>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={edit.url(
                                                            branch.id,
                                                        )}
                                                    >
                                                        Editar
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </ContentCard>
            </PageContainer>
        </>
    );
}

BusinessSettingsBranchesIndex.layout = {
    title: 'Sucursales',
    breadcrumbs: [
        {
            title: 'Configuración',
            href: business.settings.index(),
        },
        {
            title: 'Sucursales',
            href: business.settings.branches.index(),
        },
    ],
};
