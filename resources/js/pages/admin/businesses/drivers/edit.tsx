import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    BusinessDriverForm,
    type BusinessDriverFormOptions,
} from '@/apps/admin/businesses/driver-form';
import { ConfirmDialog } from '@/components/dialogs/modal';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { BackButton } from '@/components/navigation/back-button';
import { Button } from '@/components/ui/button';
import admin from '@/routes/admin';
import {
    destroy,
    index as driversIndex,
    update,
} from '@/routes/admin/businesses/drivers';

type DriverDetail = {
    id: number;
    user: {
        first_name: string;
        last_name: string;
        email: string;
        phone: string;
    } | null;
    branch_ids: number[];
};

type Props = {
    business: { id: number; name: string };
    driver: DriverDetail;
    options: BusinessDriverFormOptions;
};

export default function AdminBusinessDriversEdit({
    business,
    driver,
    options,
}: Props) {
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [loading, setLoading] = useState(false);

    return (
        <>
            <Head title={`Editar repartidor · ${business.name}`} />
            <PageContainer>
                <PageHeader
                    title="Editar repartidor"
                    description={business.name}
                    actions={
                        <Button
                            type="button"
                            variant="danger"
                            onClick={() => setConfirmOpen(true)}
                        >
                            Quitar de la empresa
                        </Button>
                    }
                />
                <ContentCard>
                    <BusinessDriverForm
                        options={options}
                        driver={{
                            first_name: driver.user?.first_name ?? '',
                            last_name: driver.user?.last_name ?? '',
                            email: driver.user?.email ?? '',
                            phone: driver.user?.phone ?? '',
                            branch_ids: driver.branch_ids,
                        }}
                        action={update({
                            business: business.id,
                            driver: driver.id,
                        })}
                        submitLabel="Guardar cambios"
                        cancelSlot={
                            <BackButton href={driversIndex.url(business.id)} />
                        }
                    />
                </ContentCard>
            </PageContainer>

            <ConfirmDialog
                open={confirmOpen}
                onOpenChange={setConfirmOpen}
                title="Quitar repartidor"
                description="Dejará de recibir pedidos de esta empresa. No se elimina su cuenta."
                confirmLabel="Quitar"
                variant="danger"
                loading={loading}
                onConfirm={() => {
                    setLoading(true);
                    router.delete(
                        destroy.url({
                            business: business.id,
                            driver: driver.id,
                        }),
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

AdminBusinessDriversEdit.layout = {
    title: 'Editar repartidor',
    breadcrumbs: [
        {
            title: 'Empresas',
            href: admin.businesses.index(),
        },
        {
            title: 'Editar repartidor',
            href: '#',
        },
    ],
};
