import { Head, Link } from '@inertiajs/react';
import {
    EmployeeForm
    
} from '@/apps/business/components/employee-form';
import type {EmployeeFormOptions} from '@/apps/business/components/employee-form';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { Button } from '@/components/ui/button';
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
                <PageHeader title="Nuevo empleado" />
                <ContentCard>
                    <EmployeeForm
                        options={options}
                        action={store()}
                        submitLabel="Crear empleado"
                        cancelSlot={
                            <Button variant="outline" asChild>
                                <Link href={index.url()}>Cancelar</Link>
                            </Button>
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
