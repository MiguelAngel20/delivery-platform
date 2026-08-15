import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    EmployeeForm
    
} from '@/apps/business/components/employee-form';
import type {EmployeeFormOptions} from '@/apps/business/components/employee-form';
import { ConfirmDialog } from '@/components/dialogs/modal';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import business from '@/routes/business';
import {
    activate,
    deactivate,
    index,
    update,
} from '@/routes/business/employees';

type EmployeeDetail = {
    id: number;
    role: string;
    status: string;
    user: {
        first_name: string;
        last_name: string;
        email: string;
        phone: string;
    };
    branch_ids: number[];
};

type Props = {
    employee: EmployeeDetail;
    options: EmployeeFormOptions;
};

export default function BusinessEmployeesEdit({ employee, options }: Props) {
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const isActive = employee.status === 'active';

    return (
        <>
            <Head title="Editar empleado" />
            <PageContainer>
                <PageHeader
                    title="Editar empleado"
                    actions={
                        <Button
                            type="button"
                            variant={isActive ? 'danger' : 'primary'}
                            onClick={() => setConfirmOpen(true)}
                        >
                            {isActive ? 'Desactivar' : 'Reactivar'}
                        </Button>
                    }
                />
                <ContentCard>
                    <EmployeeForm
                        options={options}
                        employee={{
                            id: employee.id,
                            first_name: employee.user.first_name,
                            last_name: employee.user.last_name,
                            email: employee.user.email,
                            phone: employee.user.phone,
                            role: employee.role,
                            status: employee.status,
                            branch_ids: employee.branch_ids,
                        }}
                        action={update(employee.id)}
                        submitLabel="Guardar cambios"
                        cancelSlot={
                            <Button variant="outline" asChild>
                                <Link href={index.url()}>Volver</Link>
                            </Button>
                        }
                    />
                </ContentCard>
            </PageContainer>

            <ConfirmDialog
                open={confirmOpen}
                onOpenChange={setConfirmOpen}
                title={isActive ? 'Desactivar empleado' : 'Reactivar empleado'}
                description={
                    isActive
                        ? 'El empleado dejará de acceder a esta empresa. No se eliminará el usuario.'
                        : 'El empleado recuperará acceso según su rol y sucursales.'
                }
                confirmLabel={isActive ? 'Desactivar' : 'Reactivar'}
                variant={isActive ? 'danger' : 'primary'}
                loading={loading}
                onConfirm={() => {
                    setLoading(true);
                    router.post(
                        isActive
                            ? deactivate.url(employee.id)
                            : activate.url(employee.id),
                        {},
                        {
                            preserveScroll: true,
                            onFinish: () => {
                                setLoading(false);
                                setConfirmOpen(false);
                            },
                        },
                    );
                }}
            />
        </>
    );
}

BusinessEmployeesEdit.layout = {
    title: 'Editar empleado',
    breadcrumbs: [
        {
            title: 'Empleados',
            href: business.employees.index(),
        },
        {
            title: 'Editar',
            href: '#',
        },
    ],
};
