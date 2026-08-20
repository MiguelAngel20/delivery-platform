import { Head, Link } from '@inertiajs/react';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import business from '@/routes/business';

type BusinessInfo = {
    name: string;
    delivery_mode_label: string;
    uses_own_drivers: boolean;
};

type Limits = {
    max_employees_per_branch: number;
    can_add_business_admin: boolean;
    branch_employee_usage: Array<{
        branch_id: number;
        branch_name: string;
        used: number;
        max: number;
        remaining: number;
    }>;
};

type Props = {
    business: BusinessInfo;
    limits: Limits;
};

export default function BusinessSettingsIndex({ business: businessInfo, limits }: Props) {
    const employeeSummary = limits.branch_employee_usage
        .map(
            (usage) =>
                `${usage.branch_name}: ${usage.used}/${usage.max} empleados`,
        )
        .join(' · ');

    return (
        <>
            <Head title="Configuración" />
            <PageContainer>
                <PageHeader
                    title="Configuración"
                    description={businessInfo.name}
                />

                <div className="grid gap-4 md:grid-cols-2">
                    <ContentCard
                        title="Información del negocio"
                        description="Nombre, logo y datos de contacto."
                        actions={
                            <Button asChild variant="outline" size="sm">
                                <Link href={business.settings.business.edit.url()}>
                                    Editar
                                </Link>
                            </Button>
                        }
                    >
                        <p className="text-sm text-[#64748B]">
                            Actualiza el perfil público de tu establecimiento.
                        </p>
                    </ContentCard>

                    <ContentCard
                        title="Sucursales"
                        description="Dirección, horario y datos operativos."
                        actions={
                            <Button asChild variant="outline" size="sm">
                                <Link
                                    href={business.settings.branches.index.url()}
                                >
                                    Gestionar
                                </Link>
                            </Button>
                        }
                    >
                        <p className="text-sm text-[#64748B]">
                            Edita las sucursales asignadas a tu cuenta.
                        </p>
                    </ContentCard>

                    <ContentCard
                        title="Empleados"
                        description={`Máx. ${limits.max_employees_per_branch} empleados por sucursal${limits.can_add_business_admin ? ' · 1 admin por sucursal' : ''}.`}
                        actions={
                            <Button asChild variant="outline" size="sm">
                                <Link href={business.employees.index.url()}>
                                    Gestionar
                                </Link>
                            </Button>
                        }
                    >
                        <p className="text-sm text-[#64748B]">
                            {employeeSummary || 'Crea cuentas para tu equipo.'}
                        </p>
                    </ContentCard>

                    {businessInfo.uses_own_drivers ? (
                        <ContentCard
                            title="Repartidores propios"
                            description={`Modalidad: ${businessInfo.delivery_mode_label}.`}
                            actions={
                                <Button asChild variant="outline" size="sm">
                                    <Link href={business.drivers.index.url()}>
                                        Gestionar
                                    </Link>
                                </Button>
                            }
                        >
                            <p className="text-sm text-[#64748B]">
                                Agrega repartidores y asígnalos por sucursal.
                            </p>
                        </ContentCard>
                    ) : null}

                    <ContentCard
                        title="Notificaciones"
                        description="Push y preferencias operativas."
                        actions={
                            <Button asChild variant="outline" size="sm">
                                <Link
                                    href={business.settings.notifications.edit.url()}
                                >
                                    Configurar
                                </Link>
                            </Button>
                        }
                    >
                        <p className="text-sm text-[#64748B]">
                            Activa avisos de nuevas comandas, cancelaciones e
                            incidencias.
                        </p>
                    </ContentCard>
                </div>
            </PageContainer>
        </>
    );
}

BusinessSettingsIndex.layout = {
    title: 'Configuración',
    breadcrumbs: [
        {
            title: 'Configuración',
            href: business.settings.index(),
        },
    ],
};
