import { Head, Link } from '@inertiajs/react';
import { StatusBadge } from '@/components/data-display/status-badge';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { BackButton } from '@/components/navigation/back-button';
import { Button } from '@/components/ui/button';
import business from '@/routes/business';
import { create, edit } from '@/routes/business/drivers';

type DriverRow = {
    id: number;
    approval_status: string;
    availability_status_label: string;
    user: {
        name: string;
        email: string;
        phone: string | null;
    } | null;
    branches: Array<{ id: number; name: string }>;
};

type Props = {
    drivers: DriverRow[];
};

export default function BusinessDriversIndex({ drivers }: Props) {
    return (
        <>
            <Head title="Repartidores" />
            <PageContainer>
                <PageHeader
                    title="Repartidores"
                    description="Repartidores propios de tu empresa."
                    actions={
                        <>
                            <BackButton href={business.settings.index.url()} />
                            <Button asChild>
                                <Link href={create.url()}>+ Agregar repartidor</Link>
                            </Button>
                        </>
                    }
                />
                <ContentCard>
                    {drivers.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Aún no tienes repartidores propios asociados.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border border-[#E2E8F0]">
                            <table className="min-w-full text-sm">
                                <thead className="bg-[#F8FAFC] text-left text-[#64748B]">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">
                                            Nombre
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Correo
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Teléfono
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Sucursales
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Disponibilidad
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {drivers.map((driver) => (
                                        <tr
                                            key={driver.id}
                                            className="border-t border-[#E2E8F0]"
                                        >
                                            <td className="px-4 py-3 font-medium text-navy">
                                                {driver.user?.name ?? '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {driver.user?.email}
                                            </td>
                                            <td className="px-4 py-3">
                                                {driver.user?.phone ?? '—'}
                                            </td>
                                            <td className="px-4 py-3 text-[#475569]">
                                                {driver.branches.length > 0
                                                    ? driver.branches
                                                          .map(
                                                              (branch) =>
                                                                  branch.name,
                                                          )
                                                          .join(', ')
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    tone={
                                                        driver.approval_status ===
                                                        'approved'
                                                            ? 'success'
                                                            : 'warning'
                                                    }
                                                >
                                                    {
                                                        driver.availability_status_label
                                                    }
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
                                                            driver.id,
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

BusinessDriversIndex.layout = {
    title: 'Repartidores',
    breadcrumbs: [
        {
            title: 'Configuración',
            href: business.settings.index(),
        },
        {
            title: 'Repartidores',
            href: business.drivers.index(),
        },
    ],
};
