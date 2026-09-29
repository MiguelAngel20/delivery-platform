import { Head, Link, usePage } from '@inertiajs/react';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { canAdmin  } from '@/lib/admin-access';
import type {AdminAccess} from '@/lib/admin-access';
import admin from '@/routes/admin';
import { index as adminUsersIndex } from '@/routes/admin/settings/admins';

export default function AdminSettingsIndex() {
    const { adminAccess } = usePage().props as {
        adminAccess: AdminAccess | null;
    };
    const canUpdateSettings = canAdmin(adminAccess, 'settings', 'update');

    return (
        <>
            <Head title="Configuración" />
            <PageContainer>
                <PageHeader title="Configuración" />
                <div className="space-y-4">
                    {adminAccess?.is_owner ? (
                        <ContentCard
                            title="Administradores"
                            description="Cuentas que entran a este panel con permisos limitados"
                            actions={
                                <Button asChild variant="outline" size="sm">
                                    <Link href={adminUsersIndex.url()}>
                                        Administrar
                                    </Link>
                                </Button>
                            }
                        >
                            <p className="text-sm text-muted-foreground">
                                Elige qué secciones puede abrir cada persona y
                                si puede ver, crear, editar o eliminar. Tu
                                cuenta conserva el acceso completo.
                            </p>
                        </ContentCard>
                    ) : null}

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

                    {canUpdateSettings ? (
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
                    ) : null}
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
