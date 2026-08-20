import { Head, Link } from '@inertiajs/react';
import {
    BusinessDriverForm,
    type BusinessDriverFormOptions,
} from '@/apps/admin/businesses/driver-form';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import admin from '@/routes/admin';
import { index as driversIndex, store } from '@/routes/admin/businesses/drivers';

type Props = {
    business: { id: number; name: string };
    options: BusinessDriverFormOptions;
};

export default function AdminBusinessDriversCreate({
    business,
    options,
}: Props) {
    return (
        <>
            <Head title={`Agregar repartidor · ${business.name}`} />
            <PageContainer>
                <PageHeader
                    title="Agregar repartidor"
                    description={business.name}
                />
                <ContentCard>
                    <BusinessDriverForm
                        options={options}
                        action={store(business.id)}
                        submitLabel="Guardar repartidor"
                        cancelSlot={
                            <Button variant="outline" asChild>
                                <Link href={driversIndex.url(business.id)}>
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

AdminBusinessDriversCreate.layout = {
    title: 'Agregar repartidor',
    breadcrumbs: [
        {
            title: 'Empresas',
            href: admin.businesses.index(),
        },
        {
            title: 'Agregar repartidor',
            href: '#',
        },
    ],
};
