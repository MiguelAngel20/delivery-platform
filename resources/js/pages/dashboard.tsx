import { Head } from '@inertiajs/react';
import { StatCard } from '@/components/data-display/stat-card';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { dashboard } from '@/routes';

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />
            <PageContainer>
                <PageHeader
                    title="Dashboard"
                    description="Punto de entrada autenticado del starter kit. Las interfaces de RIDE están separadas por canal."
                />
                <div className="grid gap-4 md:grid-cols-3">
                    <StatCard
                        title="Cliente"
                        value="—"
                        description="/customer"
                    />
                    <StatCard
                        title="Negocio"
                        value="—"
                        description="/business"
                    />
                    <StatCard title="Admin" value="—" description="/admin" />
                </div>
                <ContentCard>
                    <p className="text-sm text-muted-foreground">
                        Este panel se mantiene como landing autenticado hasta
                        que el acceso se dirija por rol.
                    </p>
                </ContentCard>
            </PageContainer>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
