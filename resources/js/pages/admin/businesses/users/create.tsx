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
import admin from '@/routes/admin';
import { index as usersIndex, store } from '@/routes/admin/businesses/users';

type Props = {
    business: { id: number; name: string };
    options: EmployeeFormOptions;
};

export default function AdminBusinessUsersCreate({ business, options }: Props) {
    return (
        <>
            <Head title={`Agregar usuario · ${business.name}`} />
            <PageContainer>
                <PageHeader
                    title="Agregar usuario"
                    description={business.name}
                />
                <ContentCard>
                    <EmployeeForm
                        options={options}
                        action={store(business.id)}
                        submitLabel="Guardar usuario"
                        cancelSlot={
                            <Button variant="outline" asChild>
                                <Link href={usersIndex.url(business.id)}>
                                    Cancelar
                                </Link>
                            </Button>
                        }
                    />
                </ContentCard>
            </PageContainer>
        </>
    );
}

AdminBusinessUsersCreate.layout = {
    title: 'Agregar usuario',
    breadcrumbs: [
        {
            title: 'Empresas',
            href: admin.businesses.index(),
        },
        {
            title: 'Agregar usuario',
            href: '#',
        },
    ],
};
