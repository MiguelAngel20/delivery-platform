import { Head } from '@inertiajs/react';
import { PageContainer, PageHeader } from '@/components/layout/page';
import admin from '@/routes/admin';

export default function AdminReportsIndex() {
    return (
        <>
            <Head title="Reportes" />
            <PageContainer>
                <PageHeader title="Reportes" />
            </PageContainer>
        </>
    );
}

AdminReportsIndex.layout = {
    title: 'Reportes',
    breadcrumbs: [
        {
            title: 'Reportes',
            href: admin.reports.index(),
        },
    ],
};
