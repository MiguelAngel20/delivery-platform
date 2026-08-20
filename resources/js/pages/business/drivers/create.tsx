import { Head } from '@inertiajs/react';
import {
    BusinessDriverForm,
    type BusinessDriverFormOptions,
} from '@/apps/admin/businesses/driver-form';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { BackButton } from '@/components/navigation/back-button';
import business from '@/routes/business';
import { index, store } from '@/routes/business/drivers';

type Props = {
    options: BusinessDriverFormOptions;
};

export default function BusinessDriversCreate({ options }: Props) {
    return (
        <>
            <Head title="Agregar repartidor" />
            <PageContainer>
                <PageHeader
                    title="Agregar repartidor"
                    actions={<BackButton href={index.url()} />}
                />
                <ContentCard>
                    <BusinessDriverForm
                        options={options}
                        action={store()}
                        submitLabel="Guardar repartidor"
                        cancelSlot={
                            <BackButton href={index.url()} label="Cancelar" />
                        }
                    />
                </ContentCard>
            </PageContainer>
        </>
    );
}

BusinessDriversCreate.layout = {
    title: 'Agregar repartidor',
    breadcrumbs: [
        {
            title: 'Repartidores',
            href: business.drivers.index(),
        },
        {
            title: 'Agregar',
            href: '#',
        },
    ],
};
