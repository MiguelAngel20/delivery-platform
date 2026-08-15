import { Head, Link } from '@inertiajs/react';
import { StatusBadge } from '@/components/data-display/status-badge';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { useAdminOrderEvents } from '@/hooks/realtime/use-order-realtime';
import admin from '@/routes/admin';
import { index } from '@/routes/admin/drivers';

type DriverDetail = {
    id: number;
    name: string | null;
    email: string | null;
    phone: string | null;
    user_status_label: string | null;
    approval_status_label: string | null;
    availability_status_label: string | null;
    offered_orders: number;
    accepted_orders: number;
    rejected_orders: number;
    completed_orders: number;
    cancelled_orders: number;
    responsible_cancellations: number;
    incident_count: number;
    responsible_incidents: number;
    average_rating: string | number | null;
    total_ratings: number;
    trust_score: string | number | null;
    quality_label?: string | null;
    requires_review: boolean;
    last_recalculated_at: string | null;
};

type Props = {
    driver: DriverDetail;
};

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('es-MX', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

export default function AdminDriverShow({ driver }: Props) {
    useAdminOrderEvents(true, ['driver']);

    const metrics: Array<{ label: string; value: string | number }> = [
        { label: 'Ofertados', value: driver.offered_orders },
        { label: 'Aceptados', value: driver.accepted_orders },
        { label: 'Rechazados', value: driver.rejected_orders },
        { label: 'Pedidos completados', value: driver.completed_orders },
        { label: 'Cancelados', value: driver.cancelled_orders },
        { label: 'Cancelaciones atribuibles', value: driver.responsible_cancellations },
        { label: 'Incidencias', value: driver.incident_count },
        { label: 'Incidencias atribuibles', value: driver.responsible_incidents },
        {
            label: 'Rating promedio',
            value: driver.average_rating
                ? `${driver.average_rating} ★`
                : 'Sin calificaciones',
        },
        { label: 'Total ratings', value: driver.total_ratings },
        { label: 'Trust Score', value: driver.trust_score ?? '—' },
    ];

    return (
        <>
            <Head title={driver.name ?? 'Repartidor'} />
            <PageContainer>
                <PageHeader
                    title={driver.name ?? 'Repartidor'}
                    description={`${driver.email ?? ''} · ${driver.phone ?? ''}`}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={index.url()}>Volver</Link>
                        </Button>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-2">
                    <section className="space-y-3 rounded-xl border border-border bg-white p-4">
                        <h2 className="font-semibold text-navy">Cuenta</h2>
                        <dl className="space-y-2 text-sm">
                            <div className="flex justify-between gap-3">
                                <dt>Estado de usuario</dt>
                                <dd>{driver.user_status_label ?? '—'}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt>Aprobación</dt>
                                <dd>{driver.approval_status_label ?? '—'}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt>Disponibilidad</dt>
                                <dd>
                                    {driver.availability_status_label ?? '—'}
                                </dd>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <dt>Nivel de calidad</dt>
                                <dd>
                                    {driver.quality_label ?? '—'}
                                </dd>
                            </div>
                            {driver.requires_review ? (
                                <p className="rounded-md border border-warning/30 bg-warning/10 px-3 py-2 text-warning-foreground">
                                    Requires Review
                                </p>
                            ) : (
                                <StatusBadge tone="success">OK</StatusBadge>
                            )}
                            <div className="flex justify-between gap-3">
                                <dt>Último recálculo</dt>
                                <dd>
                                    {formatDateTime(driver.last_recalculated_at)}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section className="space-y-3 rounded-xl border border-border bg-white p-4">
                        <h2 className="font-semibold text-navy">Métricas</h2>
                        <p className="text-xs text-muted-foreground">
                            Calculadas por el sistema. No se editan manualmente.
                        </p>
                        <dl className="space-y-2 text-sm">
                            {metrics.map((item) => (
                                <div
                                    key={item.label}
                                    className="flex justify-between gap-3"
                                >
                                    <dt>{item.label}</dt>
                                    <dd className="font-medium text-navy">
                                        {item.value}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </section>
                </div>
            </PageContainer>
        </>
    );
}

AdminDriverShow.layout = {
    title: 'Repartidor',
    breadcrumbs: [
        {
            title: 'Repartidores',
            href: admin.drivers.index(),
        },
        {
            title: 'Detalle',
            href: '#',
        },
    ],
};
