import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { StatusBadge } from '@/components/data-display/status-badge';
import { FormField } from '@/components/forms/form-field';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { BackButton } from '@/components/navigation/back-button';
import { OrderActionDialog } from '@/components/orders/order-action-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useBusinessOrderEvents } from '@/hooks/realtime/use-order-realtime';
import { formatMoney } from '@/lib/money';
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
    customer: {
        name?: string | null;
        phone?: string | null;
        completed_orders?: number;
    };
    driver?: { id: number; name: string } | null;
    restaurant: { branch_name?: string | null };
    delivery_address?: { address_text: string; reference?: string | null } | null;
    items: Array<{
        id: number;
        product_name: string;
        quantity: string;
        subtotal: string;
        notes?: string | null;
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
    const rejectForm = useForm({ reason: '' });

    useBusinessOrderEvents({
        businessId: realtime?.business_id,
        branchIds: realtime?.branch_ids ?? [],
        only: ['order'],
        playSoundOnCreate: false,
    });

    return (
        <>
            <Head title={`Pedido ${order.order_number}`} />
            <PageContainer>
                <PageHeader
                    title={`#${order.order_number}`}
                    description={`${order.customer.name ?? 'Cliente'} · ${order.restaurant.branch_name ?? ''}`}
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
                        <h2 className="font-semibold text-foreground">Cancelación</h2>
                        <p className="mt-1 text-muted-foreground">
                            {order.cancellation.cancelled_by_type_label} ·{' '}
                            {order.cancellation.reason_code_label}
                        </p>
                    </section>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-2">
                    <section className="space-y-3 rounded-xl border border-border bg-surface p-4">
                        <h2 className="font-semibold text-foreground">Productos</h2>
                        <ul className="space-y-3 text-sm">
                            {order.items.map((item) => (
                                <li key={item.id} className="space-y-1">
                                    <div className="flex justify-between gap-3">
                                        <span>
                                            {item.quantity}x {item.product_name}
                                        </span>
                                        <span>
                                            {formatMoney(item.subtotal)}
                                        </span>
                                    </div>
                                    {item.options.map((option, index) => (
                                        <p
                                            key={`${item.id}-${index}`}
                                            className="text-xs font-medium text-primary"
                                        >
                                            {option.display}
                                        </p>
                                    ))}
                                    {item.notes ? (
                                        <p className="text-xs text-muted-foreground">
                                            Nota: {item.notes}
                                        </p>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                        <p className="border-t border-border pt-3 text-sm text-foreground">
                            <span className="flex justify-between gap-3">
                                <span>Productos</span>
                                <span>
                                    {formatMoney(
                                        order.financial?.products_amount ??
                                            order.subtotal_after_discount ??
                                            0,
                                    )}
                                </span>
                            </span>
                            <span className="mt-1 flex justify-between gap-3">
                                <span>Servicio RIDE</span>
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
                                        order.financial?.customer_total ??
                                            order.total,
                                    )}
                                </span>
                            </span>
                        </p>
                        <div className="space-y-1 border-t border-border pt-3 text-sm text-muted-foreground">
                            <p>
                                Pago:{' '}
                                {order.financial?.payment_method_label ??
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
                    </section>

                    <section className="space-y-4 rounded-xl border border-border bg-surface p-4">
                        <div>
                            <h2 className="font-semibold text-foreground">Entrega</h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {order.delivery_address?.address_text}
                            </p>
                            {order.delivery_address?.reference ? (
                                <p className="text-sm text-muted-foreground">
                                    Ref: {order.delivery_address.reference}
                                </p>
                            ) : null}
                            <p className="mt-2 text-sm text-muted-foreground">
                                Tel: {order.customer.phone ?? '—'}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {order.customer.completed_orders ?? 0} pedidos
                                completados
                            </p>
                            {order.driver ? (
                                <p className="mt-2 text-sm font-medium text-foreground">
                                    Repartidor asignado: {order.driver.name}
                                </p>
                            ) : (
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Sin repartidor asignado
                                </p>
                            )}
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
                                                onClick={() => setMinutes(option)}
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
                                        onChange={(event) =>
                                            setMinutes(Number(event.target.value))
                                        }
                                    />
                                    <Button
                                        type="button"
                                        className="w-full"
                                        onClick={() =>
                                            router.post(accept.url(order.order_number), {
                                                estimated_preparation_minutes: minutes,
                                            })
                                        }
                                    >
                                        Aceptar pedido
                                    </Button>
                                </div>

                                <div className="space-y-2 border-t border-border pt-4">
                                    <FormField label="Motivo de rechazo" required>
                                        <Textarea
                                            value={rejectForm.data.reason}
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
                                        disabled={rejectForm.processing}
                                        onClick={() =>
                                            rejectForm.post(
                                                reject.url(order.order_number),
                                            )
                                        }
                                    >
                                        Rechazar
                                    </Button>
                                </div>
                            </>
                        ) : null}

                        {['preparing', 'driver_assigned', 'driver_at_business'].includes(
                            order.order_status,
                        ) ? (
                            <Button
                                type="button"
                                className="w-full"
                                onClick={() =>
                                    router.post(ready.url(order.order_number))
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
