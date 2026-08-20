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
import business from '@/routes/business';
import { destroy, index, update } from '@/routes/business/drivers';

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
    driver: DriverDetail;
    options: BusinessDriverFormOptions;
};

export default function BusinessDriversEdit({ driver, options }: Props) {
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [loading, setLoading] = useState(false);

    return (
        <>
            <Head title="Editar repartidor" />
            <PageContainer>
                <PageHeader
                    title="Editar repartidor"
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
                        action={update(driver.id)}
                        submitLabel="Guardar cambios"
                        cancelSlot={<BackButton href={index.url()} />}
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
                    router.delete(destroy.url(driver.id), {
                        preserveScroll: true,
                        onFinish: () => {
                            setLoading(false);
                            setConfirmOpen(false);
                        },
                    });
                }}
            />
        </>
    );
}

BusinessDriversEdit.layout = {
    title: 'Editar repartidor',
    breadcrumbs: [
        {
            title: 'Repartidores',
            href: business.drivers.index(),
        },
        {
            title: 'Editar',
            href: '#',
        },
    ],
};
