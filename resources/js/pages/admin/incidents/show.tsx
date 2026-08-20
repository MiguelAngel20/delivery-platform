import { Head, useForm } from '@inertiajs/react';
import { StatusBadge } from '@/components/data-display/status-badge';
import type { StatusTone } from '@/components/data-display/status-badge';
import { FormField } from '@/components/forms/form-field';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { BackButton } from '@/components/navigation/back-button';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useAdminOrderEvents } from '@/hooks/realtime/use-order-realtime';
import { formatMoney } from '@/lib/money';
import admin from '@/routes/admin';
import { review } from '@/routes/admin/cancellations';
import { index as incidentsIndex, resolve } from '@/routes/admin/incidents';

type Option = { value: string; label: string };

type TimelineEntry = {
    key: string;
    label: string;
    at?: string | null;
    notes?: string | null;
    current?: boolean;
};

type Cancellation = {
    id: number;
    cancelled_by_type_label: string;
    cancelled_by_name?: string | null;
    reason_code_label: string;
    reason?: string | null;
    previous_order_status_label: string;
    responsibility: string;
    responsibility_label: string;
    review_status: string;
    review_status_label: string;
    review_notes?: string | null;
    cancelled_at?: string | null;
};

type Financial = {
    settlement_status_label?: string | null;
    payment_method_label?: string | null;
    customer_total?: string;
    business_amount?: string;
    driver_earning?: string;
    transactions?: Array<{
        id: number;
        transaction_type_label: string;
        amount: string;
        status_label: string;
        description?: string | null;
    }>;
} | null;

type OrderDetail = {
    order_number: string;
    order_status_label: string;
    restaurant: { name?: string | null; branch_name?: string | null };
    customer: { name?: string | null; phone?: string | null };
    driver?: { name: string } | null;
    created_at?: string | null;
    business_accepted_at?: string | null;
    ready_at?: string | null;
    picked_up_at?: string | null;
    delivered_at?: string | null;
    estimated_preparation_exceeded?: boolean;
    timeline?: TimelineEntry[];
    cancellation?: Cancellation | null;
} | null;

type Props = {
    incident: {
        id: number;
        type_label: string;
        severity: string;
        severity_label: string;
        status: string;
        status_label: string;
        description: string;
        resolution?: string | null;
        reported_by?: string | null;
        resolved_by?: string | null;
        created_at?: string | null;
        resolved_at?: string | null;
        can_resolve: boolean;
    };
    order: OrderDetail;
    financial: Financial;
    responsibilityOptions: Option[];
};

const severityTone: Record<string, StatusTone> = {
    low: 'neutral',
    medium: 'info',
    high: 'warning',
    critical: 'danger',
};

function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('es-MX', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

export default function AdminIncidentShow({
    incident,
    order,
    financial,
    responsibilityOptions,
}: Props) {
    const resolveForm = useForm({ resolution: '' });
    const reviewForm = useForm({
        responsibility: responsibilityOptions[0]?.value ?? '',
        review_notes: '',
    });

    useAdminOrderEvents(true, ['incident', 'order', 'financial']);

    return (
        <>
            <Head title={`Incidencia #${incident.id}`} />
            <PageContainer>
                <PageHeader
                    title={`Incidencia #${incident.id}`}
                    description={incident.type_label}
                    actions={<BackButton href={incidentsIndex.url()} />}
                />

                <div className="mb-4 flex flex-wrap gap-2">
                    <StatusBadge
                        tone={severityTone[incident.severity] ?? 'neutral'}
                    >
                        {incident.severity_label}
                    </StatusBadge>
                    <StatusBadge tone="primary">{incident.status_label}</StatusBadge>
                    {order?.estimated_preparation_exceeded ? (
                        <StatusBadge tone="warning">
                            Tiempo estimado de preparación excedido
                        </StatusBadge>
                    ) : null}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <section className="space-y-3 rounded-xl border border-border bg-white p-4 text-sm">
                        <h2 className="font-semibold text-navy">Pedido</h2>
                        {order ? (
                            <dl className="space-y-2">
                                <div className="flex justify-between gap-3">
                                    <dt className="text-muted-foreground">Número</dt>
                                    <dd>#{order.order_number}</dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-muted-foreground">Estado</dt>
                                    <dd>{order.order_status_label}</dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-muted-foreground">Cliente</dt>
                                    <dd>
                                        {order.customer.name ?? '—'}
                                        {order.customer.phone
                                            ? ` · ${order.customer.phone}`
                                            : ''}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-muted-foreground">Negocio</dt>
                                    <dd>
                                        {order.restaurant.name ?? '—'}
                                        {order.restaurant.branch_name
                                            ? ` · ${order.restaurant.branch_name}`
                                            : ''}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-muted-foreground">Repartidor</dt>
                                    <dd>{order.driver?.name ?? '—'}</dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-muted-foreground">Creado</dt>
                                    <dd>{formatDateTime(order.created_at)}</dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-muted-foreground">Aceptado</dt>
                                    <dd>{formatDateTime(order.business_accepted_at)}</dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-muted-foreground">Listo</dt>
                                    <dd>{formatDateTime(order.ready_at)}</dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-muted-foreground">Recogido</dt>
                                    <dd>{formatDateTime(order.picked_up_at)}</dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-muted-foreground">Entregado</dt>
                                    <dd>{formatDateTime(order.delivered_at)}</dd>
                                </div>
                            </dl>
                        ) : (
                            <p className="text-muted-foreground">Sin pedido asociado.</p>
                        )}
                    </section>

                    <section className="space-y-3 rounded-xl border border-border bg-white p-4 text-sm">
                        <h2 className="font-semibold text-navy">Reporte</h2>
                        <p>
                            <span className="text-muted-foreground">Reportado por: </span>
                            {incident.reported_by ?? '—'}
                        </p>
                        <p>
                            <span className="text-muted-foreground">Fecha: </span>
                            {formatDateTime(incident.created_at)}
                        </p>
                        <p className="whitespace-pre-wrap">{incident.description}</p>
                        {incident.resolution ? (
                            <div className="border-t border-border pt-3">
                                <p className="font-medium text-navy">Resolución</p>
                                <p className="mt-1 whitespace-pre-wrap">
                                    {incident.resolution}
                                </p>
                                <p className="mt-1 text-muted-foreground">
                                    {incident.resolved_by ?? '—'} ·{' '}
                                    {formatDateTime(incident.resolved_at)}
                                </p>
                            </div>
                        ) : null}
                    </section>
                </div>

                {order?.timeline && order.timeline.length > 0 ? (
                    <section className="mt-4 space-y-2 rounded-xl border border-border bg-white p-4 text-sm">
                        <h2 className="font-semibold text-navy">Timeline</h2>
                        <ol className="space-y-2">
                            {order.timeline.map((entry) => (
                                <li key={`${entry.key}-${entry.at ?? ''}`}>
                                    <span className="font-medium text-navy">
                                        {entry.label}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {' '}
                                        · {formatDateTime(entry.at)}
                                    </span>
                                    {entry.notes ? (
                                        <p className="text-muted-foreground">
                                            {entry.notes}
                                        </p>
                                    ) : null}
                                </li>
                            ))}
                        </ol>
                    </section>
                ) : null}

                {order?.cancellation ? (
                    <section className="mt-4 space-y-3 rounded-xl border border-border bg-white p-4 text-sm">
                        <h2 className="font-semibold text-navy">
                            Cancelación relacionada
                        </h2>
                        <dl className="space-y-2">
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">Canceló</dt>
                                <dd>
                                    {order.cancellation.cancelled_by_type_label}
                                    {order.cancellation.cancelled_by_name
                                        ? ` · ${order.cancellation.cancelled_by_name}`
                                        : ''}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">Motivo</dt>
                                <dd>{order.cancellation.reason_code_label}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">Estado previo</dt>
                                <dd>{order.cancellation.previous_order_status_label}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">Responsabilidad</dt>
                                <dd>{order.cancellation.responsibility_label}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">Revisión</dt>
                                <dd>{order.cancellation.review_status_label}</dd>
                            </div>
                        </dl>
                        {order.cancellation.reason ? (
                            <p className="text-muted-foreground">
                                {order.cancellation.reason}
                            </p>
                        ) : null}
                        {order.cancellation.review_status === 'pending' ? (
                            <div className="space-y-3 border-t border-border pt-3">
                                <FormField label="Responsabilidad final" required>
                                    <select
                                        className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                        value={reviewForm.data.responsibility}
                                        onChange={(event) =>
                                            reviewForm.setData(
                                                'responsibility',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        {responsibilityOptions.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </FormField>
                                <FormField label="Notas internas">
                                    <Textarea
                                        value={reviewForm.data.review_notes}
                                        onChange={(event) =>
                                            reviewForm.setData(
                                                'review_notes',
                                                event.target.value,
                                            )
                                        }
                                        rows={3}
                                    />
                                </FormField>
                                <Button
                                    type="button"
                                    disabled={reviewForm.processing}
                                    onClick={() =>
                                        reviewForm.post(
                                            review.url(order.cancellation!.id),
                                        )
                                    }
                                >
                                    Guardar responsabilidad
                                </Button>
                            </div>
                        ) : order.cancellation.review_notes ? (
                            <p className="text-muted-foreground">
                                Notas: {order.cancellation.review_notes}
                            </p>
                        ) : null}
                    </section>
                ) : null}

                <section className="mt-4 space-y-3 rounded-xl border border-border bg-white p-4 text-sm">
                    <h2 className="font-semibold text-navy">Finanzas relacionadas</h2>
                    {financial ? (
                        <>
                            <p>
                                Conciliación:{' '}
                                {financial.settlement_status_label ?? '—'}
                            </p>
                            <p>Pago: {financial.payment_method_label ?? '—'}</p>
                            <dl className="space-y-1">
                                <div className="flex justify-between gap-3">
                                    <dt>Total cliente</dt>
                                    <dd>
                                        {formatMoney(financial.customer_total ?? 0)}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt>Negocio</dt>
                                    <dd>
                                        {formatMoney(financial.business_amount ?? 0)}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt>Driver</dt>
                                    <dd>
                                        {formatMoney(financial.driver_earning ?? 0)}
                                    </dd>
                                </div>
                            </dl>
                            {financial.transactions &&
                            financial.transactions.length > 0 ? (
                                <ul className="space-y-2 border-t border-border pt-3">
                                    {financial.transactions.map((tx) => (
                                        <li
                                            key={tx.id}
                                            className="flex justify-between gap-3"
                                        >
                                            <span>
                                                {tx.transaction_type_label} ·{' '}
                                                {tx.status_label}
                                            </span>
                                            <span>{formatMoney(tx.amount)}</span>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-muted-foreground">
                                    Sin movimientos financieros.
                                </p>
                            )}
                        </>
                    ) : (
                        <p className="text-muted-foreground">
                            Este pedido no tiene snapshot financiero.
                        </p>
                    )}
                </section>

                {incident.can_resolve ? (
                    <section className="mt-4 space-y-3 rounded-xl border border-border bg-white p-4">
                        <h2 className="font-semibold text-navy">Resolver incidencia</h2>
                        <FormField label="Resolución" required>
                            <Textarea
                                value={resolveForm.data.resolution}
                                onChange={(event) =>
                                    resolveForm.setData(
                                        'resolution',
                                        event.target.value,
                                    )
                                }
                                rows={4}
                            />
                        </FormField>
                        <Button
                            type="button"
                            disabled={resolveForm.processing}
                            onClick={() =>
                                resolveForm.post(resolve.url(incident.id))
                            }
                        >
                            Marcar como resuelta
                        </Button>
                    </section>
                ) : null}
            </PageContainer>
        </>
    );
}

AdminIncidentShow.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin.home() },
        { title: 'Incidencias', href: incidentsIndex.url() },
        { title: 'Detalle', href: '#' },
    ],
};
