import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { StatusBadge } from '@/components/data-display/status-badge';
import type { StatusTone } from '@/components/data-display/status-badge';
import { ProcessingOverlay } from '@/components/feedback/processing-overlay';
import { FormField } from '@/components/forms/form-field';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { BackButton } from '@/components/navigation/back-button';
import { OrderActionDialog } from '@/components/orders/order-action-dialog';
import { OrderCustomerPanel } from '@/components/orders/order-customer-panel';
import { OrderDetailPanel } from '@/components/orders/order-detail-panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useBusinessOrderEvents } from '@/hooks/realtime/use-order-realtime';
import { formatMoney } from '@/lib/money';
import { resolveRestaurantLabel } from '@/lib/restaurant-label';
import business from '@/routes/business';
import {
    accept,
    cancel as cancelOrder,
    index,
    ready,
    reject,
} from '@/routes/business/orders';
import { store as storeIncident } from '@/routes/business/orders/incidents';

type Option = { value: string; label: string };

type OrderDetail = {
    id: number;
    order_number: string;
    order_status: string;
    business_status_label: string;
    total: string;
    subtotal_after_discount?: string;
    service_fee?: string;
    payment_method_label?: string;
    estimated_preparation_minutes?: number | null;
    customer: {
        name?: string | null;
        phone?: string | null;
        reputation_label?: string | null;
        reputation_tone?: StatusTone;
        completed_orders?: number;
        is_frequent?: boolean;
    };
    driver?: { name?: string | null; phone?: string | null } | null;
    restaurant: {
        name?: string | null;
        branch_name?: string | null;
        phone?: string | null;
    };
    pickup_address?: {
        address_text?: string | null;
        google_maps_url?: string | null;
    } | null;
    delivery_address?: {
        address_text: string;
        reference?: string | null;
        google_maps_url?: string | null;
    } | null;
    items: Array<{
        id: number;
        product_name: string;
        display_name?: string | null;
        category_name?: string | null;
        subcategory_name?: string | null;
        quantity: string;
        subtotal: string;
        notes?: string | null;
        line_label?: string;
        options: Array<{ display: string }>;
    }>;
    financial?: {
        products_amount: string;
        service_fee: string;
        customer_total: string;
        payment_method_label: string;
        driver_paid_business: boolean;
    } | null;
    cancellation?: {
        cancelled_by_type_label: string;
        reason_code_label: string;
        reason?: string | null;
    } | null;
    estimated_preparation_exceeded?: boolean;
    actions: {
        business_can_cancel: boolean;
        business_can_reject: boolean;
        business_cancel_reasons: Option[];
        business_incident_types: Option[];
    };
};

type Props = {
    order: OrderDetail;
    preparationOptions: number[];
};

type BusyState = {
    title: string;
    description: string;
} | null;

export default function BusinessOrderShow({
    order,
    preparationOptions,
}: Props) {
    const { realtime } = usePage().props as {
        realtime?: {
            business_id?: number | null;
            branch_ids?: number[];
        };
    };
    const [minutes, setMinutes] = useState(20);
    const [dialog, setDialog] = useState<'cancel' | 'report' | null>(null);
    const [busy, setBusy] = useState<BusyState>(null);
    const rejectForm = useForm({ reason: '' });
    const isBusy = busy !== null || rejectForm.processing;

    useBusinessOrderEvents({
        businessId: realtime?.business_id,
        branchIds: realtime?.branch_ids ?? [],
        only: ['order'],
        playSoundOnCreate: false,
    });

    const runOrderAction = (
        title: string,
        description: string,
        url: string,
        data: Record<string, unknown> = {},
    ) => {
        if (isBusy) {
            return;
        }

        router.post(url, data, {
            onStart: () => setBusy({ title, description }),
            onFinish: () => setBusy(null),
        });
    };

    const restaurantLabel = resolveRestaurantLabel(
        order.restaurant.name,
        order.restaurant.branch_name,
    );

    return (
        <>
            <Head title={`Pedido ${order.order_number}`} />
            <PageContainer>
                <PageHeader
                    title={`#${order.order_number}`}
                    actions={<BackButton href={index.url()} />}
                />

                <div className="mb-4 flex flex-wrap items-center gap-2">
                    <StatusBadge tone="primary">
                        {order.business_status_label}
                    </StatusBadge>
                    {order.estimated_preparation_exceeded ? (
                        <StatusBadge tone="warning">
                            Tiempo estimado de preparación excedido
                        </StatusBadge>
                    ) : null}
                </div>

                {order.cancellation ? (
                    <section className="mb-4 rounded-xl border border-border bg-surface p-4 text-sm">
                        <h2 className="font-semibold text-foreground">
                            Cancelación
                        </h2>
                        <p className="mt-1 text-muted-foreground">
                            {order.cancellation.cancelled_by_type_label} ·{' '}
                            {order.cancellation.reason_code_label}
                        </p>
                    </section>
                ) : null}

                <div className="grid min-w-0 items-start gap-4 lg:grid-cols-2">
                    <OrderDetailPanel
                        orderNumber={order.order_number}
                        businessName={restaurantLabel}
                        businessPhone={order.restaurant.phone}
                        items={order.items}
                        pickupAddress={order.pickup_address}
                        footer={
                            <>
                                <p className="border-t border-border pt-3 text-sm text-foreground">
                                    <span className="flex justify-between gap-3">
                                        <span>Productos</span>
                                        <span>
                                            {formatMoney(
                                                order.financial
                                                    ?.products_amount ??
                                                    order.subtotal_after_discount ??
                                                    0,
                                            )}
                                        </span>
                                    </span>
                                    <span className="mt-1 flex justify-between gap-3">
                                        <span>Servicio ChisDrive</span>
                                        <span>
                                            {formatMoney(
                                                order.financial?.service_fee ??
                                                    order.service_fee ??
                                                    0,
                                            )}
                                        </span>
                                    </span>
                                    <span className="mt-2 flex justify-between gap-3 text-base font-semibold">
                                        <span>Total cliente</span>
                                        <span>
                                            {formatMoney(
                                                order.financial
                                                    ?.customer_total ??
                                                    order.total,
                                            )}
                                        </span>
                                    </span>
                                </p>
                                <div className="space-y-1 border-t border-border pt-3 text-sm text-muted-foreground">
                                    <p>
                                        Pago:{' '}
                                        {order.financial
                                            ?.payment_method_label ??
                                            order.payment_method_label ??
                                            'Efectivo'}
                                    </p>
                                    <p>
                                        Pago al establecimiento:{' '}
                                        {order.financial?.driver_paid_business
                                            ? 'Registrado'
                                            : 'Pendiente'}
                                    </p>
                                </div>
                            </>
                        }
                    />

                    <section className="space-y-4 rounded-xl border border-border bg-surface p-4">
                        <div className="space-y-3">
                            <h2 className="font-semibold text-foreground">
                                Operación
                            </h2>
                            <dl className="space-y-2 text-sm">
                                <div className="flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:gap-3">
                                    <dt className="text-muted-foreground">
                                        Estado
                                    </dt>
                                    <dd className="sm:text-right">
                                        <StatusBadge tone="primary">
                                            {order.business_status_label}
                                        </StatusBadge>
                                    </dd>
                                </div>
                                <div className="flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:gap-3">
                                    <dt className="text-muted-foreground">
                                        Tiempo al cliente
                                    </dt>
                                    <dd className="font-medium text-foreground sm:text-right">
                                        {order.estimated_preparation_minutes !=
                                        null
                                            ? `${order.estimated_preparation_minutes} minutos`
                                            : 'Aún no asignado'}
                                    </dd>
                                </div>
                                <div className="flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:gap-3">
                                    <dt className="text-muted-foreground">
                                        Repartidor
                                    </dt>
                                    <dd className="font-medium text-foreground sm:text-right">
                                        {order.driver?.name ?? 'Sin asignar'}
                                    </dd>
                                </div>
                                {order.driver?.phone ? (
                                    <div className="flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:gap-3">
                                        <dt className="text-muted-foreground">
                                            Tel. repartidor
                                        </dt>
                                        <dd className="font-medium text-foreground sm:text-right">
                                            {order.driver.phone}
                                        </dd>
                                    </div>
                                ) : null}
                            </dl>
                        </div>

                        <div className="space-y-3 border-t border-border pt-4">
                            <OrderCustomerPanel
                                customer={order.customer}
                                deliveryAddress={order.delivery_address}
                                orderNumber={order.order_number}
                                showReference={false}
                                footer={
                                    <div className="flex flex-wrap items-center gap-2 pt-1">
                                        <StatusBadge
                                            tone={
                                                order.customer
                                                    .reputation_tone ??
                                                'neutral'
                                            }
                                        >
                                            {order.customer.reputation_label ??
                                                'Sin reputación'}
                                        </StatusBadge>
                                        {order.customer.is_frequent ? (
                                            <StatusBadge tone="primary">
                                                Cliente frecuente
                                            </StatusBadge>
                                        ) : null}
                                        <span className="text-xs text-muted-foreground">
                                            {order.customer.completed_orders ??
                                                0}{' '}
                                            pedidos completados
                                        </span>
                                    </div>
                                }
                            />
                        </div>

                        {order.order_status === 'pending_business' &&
                        order.actions.business_can_reject ? (
                            <>
                                <div className="space-y-2">
                                    <h3 className="text-sm font-medium text-foreground">
                                        Tiempo estimado (min)
                                    </h3>
                                    <div className="flex flex-wrap gap-2">
                                        {preparationOptions.map((option) => (
                                            <Button
                                                key={option}
                                                type="button"
                                                size="sm"
                                                variant={
                                                    minutes === option
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                disabled={isBusy}
                                                onClick={() =>
                                                    setMinutes(option)
                                                }
                                            >
                                                {option}
                                            </Button>
                                        ))}
                                    </div>
                                    <Input
                                        type="number"
                                        min={1}
                                        max={180}
                                        value={minutes}
                                        disabled={isBusy}
                                        onChange={(event) =>
                                            setMinutes(
                                                Number(event.target.value),
                                            )
                                        }
                                    />
                                    <Button
                                        type="button"
                                        className="w-full"
                                        disabled={isBusy}
                                        loading={
                                            busy?.title ===
                                            'Aceptando pedido…'
                                        }
                                        onClick={() =>
                                            runOrderAction(
                                                'Aceptando pedido…',
                                                'Estamos registrando la aceptación. No cierres esta ventana.',
                                                accept.url(order.order_number),
                                                {
                                                    estimated_preparation_minutes:
                                                        minutes,
                                                },
                                            )
                                        }
                                    >
                                        Aceptar pedido
                                    </Button>
                                </div>

                                <div className="space-y-2 border-t border-border pt-4">
                                    <FormField
                                        label="Motivo de rechazo"
                                        required
                                    >
                                        <Textarea
                                            value={rejectForm.data.reason}
                                            disabled={isBusy}
                                            onChange={(event) =>
                                                rejectForm.setData(
                                                    'reason',
                                                    event.target.value,
                                                )
                                            }
                                            rows={3}
                                        />
                                    </FormField>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="w-full"
                                        disabled={isBusy}
                                        loading={rejectForm.processing}
                                        onClick={() =>
                                            rejectForm.post(
                                                reject.url(order.order_number),
                                                {
                                                    onStart: () =>
                                                        setBusy({
                                                            title: 'Rechazando pedido…',
                                                            description:
                                                                'Estamos registrando el rechazo. No cierres esta ventana.',
                                                        }),
                                                    onFinish: () =>
                                                        setBusy(null),
                                                },
                                            )
                                        }
                                    >
                                        Rechazar
                                    </Button>
                                </div>
                            </>
                        ) : null}

                        {[
                            'preparing',
                            'driver_assigned',
                            'driver_at_business',
                        ].includes(order.order_status) ? (
                            <Button
                                type="button"
                                className="w-full"
                                disabled={isBusy}
                                loading={
                                    busy?.title ===
                                    'Marcando pedido como listo…'
                                }
                                onClick={() =>
                                    runOrderAction(
                                        'Marcando pedido como listo…',
                                        'Avisando que el pedido está listo para recoger.',
                                        ready.url(order.order_number),
                                    )
                                }
                            >
                                Marcar listo para recoger
                            </Button>
                        ) : null}

                        {order.actions.business_can_cancel ? (
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full"
                                disabled={isBusy}
                                onClick={() => setDialog('cancel')}
                            >
                                Cancelar pedido
                            </Button>
                        ) : null}

                        {order.order_status !== 'pending_business' &&
                        order.order_status !== 'cancelled' &&
                        order.order_status !== 'rejected' &&
                        order.order_status !== 'delivered' ? (
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full"
                                disabled={isBusy}
                                onClick={() => setDialog('report')}
                            >
                                Reportar problema
                            </Button>
                        ) : null}
                    </section>
                </div>
            </PageContainer>

            <OrderActionDialog
                open={dialog === 'cancel'}
                onOpenChange={(open) => setDialog(open ? 'cancel' : null)}
                title="Cancelar pedido"
                description="El pedido ya fue aceptado. Selecciona un motivo."
                actionUrl={cancelOrder.url(order.order_number)}
                codeField="reason_code"
                options={order.actions.business_cancel_reasons}
                selectLabel="Motivo"
                notesLabel="Detalle (opcional)"
                notesName="reason"
                notesRequired={false}
                submitLabel="Confirmar cancelación"
                processingTitle="Cancelando pedido…"
                processingDescription="Estamos cancelando el pedido. No cierres esta ventana."
            />
            <OrderActionDialog
                open={dialog === 'report'}
                onOpenChange={(open) => setDialog(open ? 'report' : null)}
                title="Reportar problema"
                description="Quedará registrado para revisión administrativa."
                actionUrl={storeIncident.url(order.order_number)}
                codeField="type"
                options={order.actions.business_incident_types}
                selectLabel="Tipo"
                notesLabel="Descripción"
                notesName="description"
                submitLabel="Enviar reporte"
                processingTitle="Enviando reporte…"
                processingDescription="Estamos registrando el problema. No cierres esta ventana."
            />

            <ProcessingOverlay
                open={busy !== null}
                title={busy?.title}
                description={busy?.description}
            />
        </>
    );
}

BusinessOrderShow.layout = {
    breadcrumbs: [
        { title: 'Business', href: business.home.url() },
        { title: 'Pedidos', href: index.url() },
        { title: 'Detalle', href: '#' },
    ],
};
