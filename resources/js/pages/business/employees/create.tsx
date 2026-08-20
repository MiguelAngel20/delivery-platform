import { Head } from '@inertiajs/react';
import {
    EmployeeForm,
} from '@/apps/business/components/employee-form';
import type { EmployeeFormOptions } from '@/apps/business/components/employee-form';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { BackButton } from '@/components/navigation/back-button';
import business from '@/routes/business';
import { index, store } from '@/routes/business/employees';

type Props = {
    options: EmployeeFormOptions;
};

export default function BusinessEmployeesCreate({ options }: Props) {
    return (
        <>
            <Head title="Nuevo empleado" />
            <PageContainer>
                <PageHeader
                    title="Nuevo empleado"
                    actions={<BackButton href={index.url()} />}
                />
                <ContentCard>
                    <EmployeeForm
                        options={options}
                        action={store()}
                        submitLabel="Crear empleado"
                        cancelSlot={
                            <BackButton href={index.url()} label="Cancelar" />
                        }
                    />
                </ContentCard>
            </PageContainer>
        </>
    );
}

BusinessEmployeesCreate.layout = {
    title: 'Nuevo empleado',
    breadcrumbs: [
        {
            title: 'Empleados',
            href: business.employees.index(),
        },
        {
            title: 'Nuevo',
            href: business.employees.create(),
        },
    ],
};
