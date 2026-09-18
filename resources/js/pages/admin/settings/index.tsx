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
                <div className="space-y-4">
                    <ContentCard
                        title="Notificaciones"
                        description="Alertas operativas ChisDrive"
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

                    <ContentCard
                        title="Suspender actividad"
                        description="Pausa los pedidos nuevos en toda la plataforma"
                        actions={
                            <Button asChild variant="outline" size="sm">
                                <Link href="/admin/settings/activity-suspension">
                                    Configurar
                                </Link>
                            </Button>
                        }
                    >
                        <p className="text-sm text-muted-foreground">
                            Mantenimiento, lluvia intensa o fuera de horario.
                            Los clientes pueden ver menús, pero no ordenar.
                        </p>
                    </ContentCard>
                </div>
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
