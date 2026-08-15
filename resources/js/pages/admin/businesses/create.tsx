import { Head, Link } from '@inertiajs/react';
import { BusinessForm } from '@/apps/admin/businesses/business-form';
import type { BusinessFormOptions } from '@/apps/admin/businesses/types';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import admin from '@/routes/admin';
import { index, store } from '@/routes/admin/businesses';

type Props = {
    options: BusinessFormOptions;
};

export default function AdminBusinessesCreate({ options }: Props) {
    return (
        <>
            <Head title="Nueva empresa" />
            <PageContainer>
                <PageHeader title="Nueva empresa" />
                <ContentCard>
                    <BusinessForm
                        options={options}
                        action={store()}
                        submitLabel="Crear empresa"
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

AdminBusinessesCreate.layout = {
    title: 'Nueva empresa',
    breadcrumbs: [
        {
            title: 'Empresas',
            href: admin.businesses.index(),
        },
        {
            title: 'Nueva',
            href: admin.businesses.create(),
        },
    ],
};
