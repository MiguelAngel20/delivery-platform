import { Head, Link } from '@inertiajs/react';
import { BusinessForm } from '@/apps/admin/businesses/business-form';
import type {
    BusinessDetail,
    BusinessFormOptions,
} from '@/apps/admin/businesses/types';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import admin from '@/routes/admin';
import { show, update } from '@/routes/admin/businesses';

type Props = {
    business: BusinessDetail;
    options: BusinessFormOptions;
};

export default function AdminBusinessesEdit({ business, options }: Props) {
    return (
        <>
            <Head title={`Editar ${business.name}`} />
            <PageContainer>
                <PageHeader title={`Editar ${business.name}`} />
                <ContentCard>
                    <BusinessForm
                        options={options}
                        business={business}
                        action={update(business.id)}
                        submitLabel="Guardar cambios"
                        cancelSlot={
                            <Button variant="outline" asChild>
                                <Link href={show.url(business.id)}>
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

AdminBusinessesEdit.layout = {
    title: 'Editar empresa',
    breadcrumbs: [
        {
            title: 'Empresas',
            href: admin.businesses.index(),
        },
        {
            title: 'Editar',
            href: '#',
        },
    ],
};
