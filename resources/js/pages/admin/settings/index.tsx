import { Head, Link } from '@inertiajs/react';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import admin from '@/routes/admin';

export default function AdminSettingsIndex() {
    return (
        <>
            <Head title="Configuración" />
            <PageContainer>
                <PageHeader title="Configuración" />
                <ContentCard
                    title="Notificaciones"
                    description="Alertas operativas RIDE"
                    actions={
                        <Button asChild variant="outline" size="sm">
                            <Link href="/admin/settings/notifications">
                                Configurar
                            </Link>
                        </Button>
                    }
                >
                    <p className="text-sm text-muted-foreground">
                        Custom orders, pedidos PLATFORM e incidencias
                        importantes.
                    </p>
                </ContentCard>
            </PageContainer>
        </>
    );
}

AdminSettingsIndex.layout = {
    title: 'Configuración',
    breadcrumbs: [
        {
            title: 'Configuración',
            href: admin.settings.index(),
        },
    ],
};
