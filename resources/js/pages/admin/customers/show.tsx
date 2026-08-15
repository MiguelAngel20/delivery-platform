import { Form, Head, Link } from '@inertiajs/react';
import { StatusBadge } from '@/components/data-display/status-badge';
import type { StatusTone } from '@/components/data-display/status-badge';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import admin from '@/routes/admin';
import {
    blockTrust,
    index,
    unblockTrust,
} from '@/routes/admin/customers';

type CustomerDetail = {
    id: number;
    name: string | null;
    email: string | null;
    phone: string | null;
    user_status_label: string | null;
    trust_level: string;
    trust_level_label: string;
    trust_level_tone: StatusTone;
    trust_score: string | number | null;
    total_orders: number;
    completed_orders: number;
    cancelled_orders: number;
    late_cancellations: number;
    rejected_at_delivery: number;
    incident_count: number;
    responsible_incidents: number;
    payment_incidents: number;
    requires_review: boolean;
    last_recalculated_at: string | null;
};

type Props = {
    customer: CustomerDetail;
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

export default function AdminCustomerShow({ customer }: Props) {
    const metrics: Array<{ label: string; value: string | number }> = [
        { label: 'Pedidos totales', value: customer.total_orders },
        { label: 'Completados', value: customer.completed_orders },
        { label: 'Cancelaciones', value: customer.cancelled_orders },
        { label: 'Cancelaciones tardías', value: customer.late_cancellations },
        { label: 'Rechazos en entrega', value: customer.rejected_at_delivery },
        { label: 'Incidencias', value: customer.incident_count },
        { label: 'Incidencias atribuibles', value: customer.responsible_incidents },
        { label: 'Problemas de pago', value: customer.payment_incidents },
        { label: 'Trust Score', value: customer.trust_score ?? '—' },
    ];

    return (
        <>
            <Head title={customer.name ?? 'Cliente'} />
            <PageContainer>
                <PageHeader
                    title={customer.name ?? 'Cliente'}
                    description={`${customer.email ?? ''} · ${customer.phone ?? ''}`}
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
                                <dd>{customer.user_status_label ?? '—'}</dd>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <dt>Trust Level</dt>
                                <dd>
                                    <StatusBadge tone={customer.trust_level_tone}>
                                        {customer.trust_level_label}
                                    </StatusBadge>
                                </dd>
                            </div>
                            {customer.requires_review ? (
                                <p className="rounded-md border border-warning/30 bg-warning/10 px-3 py-2 text-warning-foreground">
                                    Requires Review
                                </p>
                            ) : null}
                            <div className="flex justify-between gap-3">
                                <dt>Último recálculo</dt>
                                <dd>
                                    {formatDateTime(customer.last_recalculated_at)}
                                </dd>
                            </div>
                        </dl>
                        <div className="flex flex-wrap gap-2 pt-2">
                            {customer.trust_level !== 'blocked' ? (
                                <Form {...blockTrust.form(customer.id)}>
                                    <Button type="submit" variant="destructive">
                                        Marcar BLOCKED
                                    </Button>
                                </Form>
                            ) : (
                                <Form {...unblockTrust.form(customer.id)}>
                                    <Button type="submit" variant="outline">
                                        Quitar bloqueo de reputación
                                    </Button>
                                </Form>
                            )}
                        </div>
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

AdminCustomerShow.layout = {
    title: 'Cliente',
    breadcrumbs: [
        {
            title: 'Clientes',
            href: admin.customers.index(),
        },
        {
            title: 'Detalle',
            href: '#',
        },
    ],
};
