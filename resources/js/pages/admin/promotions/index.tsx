import { Head } from '@inertiajs/react';
import { PageContainer, PageHeader } from '@/components/layout/page';
import admin from '@/routes/admin';

export default function AdminPromotionsIndex() {
    return (
        <>
            <Head title="Promociones" />
            <PageContainer>
                <PageHeader title="Promociones" />
            </PageContainer>
        </>
    );
}

AdminPromotionsIndex.layout = {
    title: 'Promociones',
    breadcrumbs: [
        {
            title: 'Promociones',
            href: admin.promotions.index(),
        },
    ],
};
