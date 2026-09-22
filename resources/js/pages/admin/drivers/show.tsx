import { Head, router, useForm } from '@inertiajs/react';
import { StatusBadge } from '@/components/data-display/status-badge';
import { FormField } from '@/components/forms/form-field';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { BackButton } from '@/components/navigation/back-button';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAdminOrderEvents } from '@/hooks/realtime/use-order-realtime';
import { formatMoney } from '@/lib/money';
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
    pays_commission: boolean;
    commission_per_order: string;
    commission_owed: string;
    commission_blocked: boolean;
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

    const commissionForm = useForm({
        pays_commission: driver.pays_commission,
        commission_per_order: driver.commission_per_order,
    });

    const metrics: Array<{ label: string; value: string | number }> = [
        { label: 'Ofertados', value: driver.offered_orders },
        { label: 'Aceptados', value: driver.accepted_orders },
        { label: 'Rechazados', value: driver.rejected_orders },
        { label: 'Pedidos completados', value: driver.completed_orders },
        { label: 'Cancelados', value: driver.cancelled_orders },
        {
            label: 'Cancelaciones atribuibles',
            value: driver.responsible_cancellations,
        },
        { label: 'Incidencias', value: driver.incident_count },
        {
            label: 'Incidencias atribuibles',
            value: driver.responsible_incidents,
        },
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
                    actions={<BackButton href={index.url()} />}
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
                                <dd>{driver.quality_label ?? '—'}</dd>
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
                        <h2 className="font-semibold text-navy">
                            Comisión ChisDrive
                        </h2>
                        <form
                            className="space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                commissionForm.put(
                                    `/admin/drivers/${driver.id}/commission`,
                                    { preserveScroll: true },
                                );
                            }}
                        >
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={commissionForm.data.pays_commission}
                                    onChange={(event) =>
                                        commissionForm.setData(
                                            'pays_commission',
                                            event.target.checked,
                                        )
                                    }
                                />
                                Este repartidor paga comisión
                            </label>
                            {commissionForm.data.pays_commission ? (
                                <FormField
                                    label="Monto por pedido ($0–$10)"
                                    error={
                                        commissionForm.errors
                                            .commission_per_order
                                    }
                                >
                                    <Input
                                        type="number"
                                        min={0}
                                        max={10}
                                        step="1"
                                        value={
                                            commissionForm.data
                                                .commission_per_order
                                        }
                                        onChange={(event) =>
                                            commissionForm.setData(
                                                'commission_per_order',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            ) : null}
                            <Button
                                type="submit"
                                disabled={commissionForm.processing}
                            >
                                Guardar comisión
                            </Button>
                        </form>

                        <div className="rounded-lg border border-border bg-muted/30 px-3 py-3 text-sm">
                            <p className="flex justify-between gap-3">
                                <span>Pendiente de verificar</span>
                                <strong>
                                    {formatMoney(driver.commission_owed)}
                                </strong>
                            </p>
                            {driver.commission_blocked ? (
                                <p className="mt-2 text-xs text-amber-700">
                                    Bloqueado para aceptar pedidos hasta
                                    verificar el pago.
                                </p>
                            ) : null}
                            <Button
                                type="button"
                                className="mt-3 w-full"
                                variant="outline"
                                disabled={Number(driver.commission_owed) <= 0}
                                onClick={() => {
                                    if (
                                        !window.confirm(
                                            `¿Marcar como pagada la comisión de ${formatMoney(driver.commission_owed)}?`,
                                        )
                                    ) {
                                        return;
                                    }

                                    router.post(
                                        `/admin/drivers/${driver.id}/commission/mark-paid`,
                                        {},
                                        { preserveScroll: true },
                                    );
                                }}
                            >
                                Marcar comisión como pagada
                            </Button>
                        </div>
                    </section>

                    <section className="space-y-3 rounded-xl border border-border bg-white p-4 lg:col-span-2">
                        <h2 className="font-semibold text-navy">Métricas</h2>
                        <p className="text-xs text-muted-foreground">
                            Calculadas por el sistema. No se editan
                            manualmente.
                        </p>
                        <dl className="grid gap-2 text-sm sm:grid-cols-2">
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
