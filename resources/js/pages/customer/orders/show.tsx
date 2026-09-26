import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { consumePendingCartClear } from '@/apps/storefront/cart/use-storefront-cart';
import { OrderStatusTimeline } from '@/apps/storefront/components/order-status-timeline';
import { StatusBadge } from '@/components/data-display/status-badge';
import { PageContainer } from '@/components/layout/page';
import { BackButton } from '@/components/navigation/back-button';
import { DriverRatingForm } from '@/components/orders/driver-rating-form';
import { OrderActionDialog } from '@/components/orders/order-action-dialog';
import { OrderItemsGrouped } from '@/components/orders/order-items-grouped';
import { Button } from '@/components/ui/button';
import { useCustomerOrderEvents } from '@/hooks/realtime/use-order-realtime';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { cart } from '@/routes';
import { cancel as cancelOrder, index } from '@/routes/customer/orders';
import { accept as acceptQuote } from '@/routes/customer/orders/quotes';
import { store as storeIncident } from '@/routes/customer/orders/incidents';
import { store as storeRating } from '@/routes/customer/orders/ratings';
import { show as showRestaurant } from '@/routes/restaurants';

type Option = { value: string; label: string };

type StatusGuidance = {
    tone: 'info' | 'success' | 'danger' | string;
    title: string;
    message: string;
    actions?: Array<{
        type: string;
        label: string;
        slug?: string | null;
    }>;
};

type OrderDetail = {
    id: number;
    order_number: string;
    order_status: string;
    order_status_label: string;
    estimated_preparation_minutes?: number | null;
    total: string;
    payment_method_label: string;
    restaurant: { name?: string | null; slug?: string | null };
    driver?: { id: number; name: string } | null;
    delivery_address?: { address_text: string; reference?: string | null } | null;
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
    customer_timeline: Array<{
        key: string;
        label: string;
        done: boolean;
        current?: boolean;
    }>;
    customer_status_guidance?: StatusGuidance | null;
    cancellation?: {
        cancelled_by_type_label: string;
        reason_code_label: string;
        reason?: string | null;
    } | null;
    actions: {
        customer_can_cancel: boolean;
        customer_can_report_problem: boolean;
        customer_can_accept_quote?: boolean;
        customer_cancel_reasons: Option[];
        customer_incident_types: Option[];
    };
    pending_quote?: {
        total: string;
        subtotal: string;
        service_fee: string;
    } | null;
    driver_rating?: { overall_rating: number; comment?: string | null } | null;
    can_rate_driver?: boolean;
};

type Props = {
    order: OrderDetail;
};

function guidanceToneClasses(tone: string): string {
    switch (tone) {
        case 'success':
            return 'border-success/30 bg-success/5';
        case 'danger':
            return 'border-destructive/40 bg-destructive/5';
        default:
            return 'border-primary/30 bg-primary/5';
    }
}

function guidanceActionHref(action: {
    type: string;
    slug?: string | null;
}): string | null {
    if (action.type === 'cart') {
        return cart.url();
    }

    if (action.type === 'restaurant' && action.slug) {
        return showRestaurant.url(action.slug);
    }

    return null;
}

export default function CustomerOrderShow({ order }: Props) {
    const { auth, realtime } = usePage().props as {
        auth: Auth;
        realtime?: { customer_id?: number | null };
    };
    const [dialog, setDialog] = useState<'cancel' | 'report' | null>(null);
    const guidance = order.customer_status_guidance ?? null;

    useEffect(() => {
        consumePendingCartClear();
    }, []);

    useCustomerOrderEvents(realtime?.customer_id, order.id, {
        only: ['order'],
        userId: auth.user?.id,
    });

    return (
        <>
            <Head title={`Pedido ${order.order_number}`} />
            <PageContainer className="gap-5 px-4 py-4 md:px-6">
                <div className="space-y-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="text-2xl font-semibold text-navy">
                            #{order.order_number}
                        </h1>
                        <StatusBadge tone="primary">
                            {order.order_status_label}
                        </StatusBadge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {order.restaurant.name}
                        {order.estimated_preparation_minutes
                            ? ` · Tiempo estimado: ${order.estimated_preparation_minutes} min`
                            : ''}
                    </p>
                    {order.driver ? (
                        <p className="text-sm font-medium text-navy">
                            Repartidor: {order.driver.name}
                        </p>
                    ) : null}
                </div>

                {order.pending_quote && order.actions.customer_can_accept_quote ? (
                    <section className="space-y-3 rounded-xl border border-primary/30 bg-surface p-4">
                        <h2 className="font-semibold text-navy">
                            El precio de tu pedido cambió
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Anterior: {formatMoney(order.total)}
                        </p>
                        <p className="text-xl font-semibold text-navy">
                            Nuevo: {formatMoney(order.pending_quote.total)}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Form {...acceptQuote.form(order.order_number)}>
                                <Button type="submit">Aceptar nuevo total</Button>
                            </Form>
                            {order.actions.customer_can_cancel ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setDialog('cancel')}
                                >
                                    Cancelar pedido
                                </Button>
                            ) : null}
                        </div>
                    </section>
                ) : null}

                {order.cancellation ? (
                    <section className="rounded-xl border border-border bg-surface p-4 text-sm">
                        <h2 className="font-semibold text-navy">Cancelación</h2>
                        <p className="mt-1 text-muted-foreground">
                            {order.cancellation.cancelled_by_type_label} ·{' '}
                            {order.cancellation.reason_code_label}
                        </p>
                        {order.cancellation.reason ? (
                            <p className="mt-1 text-muted-foreground">
                                {order.cancellation.reason}
                            </p>
                        ) : null}
                    </section>
                ) : null}

                <section className="rounded-xl border border-border bg-surface p-4">
                    <h2 className="mb-4 font-semibold text-navy">Seguimiento</h2>
                    {guidance ? (
                        <div
                            className={cn(
                                'mb-4 space-y-3 rounded-xl border p-3',
                                guidanceToneClasses(guidance.tone),
                            )}
                        >
                            <div className="space-y-1">
                                <p className="text-sm font-semibold text-navy">
                                    {guidance.title}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {guidance.message}
                                </p>
                            </div>
                            {(guidance.actions?.length ?? 0) > 0 ? (
                                <div className="flex flex-wrap gap-2">
                                    {guidance.actions!.map((action) => {
                                        const href = guidanceActionHref(action);

                                        if (!href) {
                                            return null;
                                        }

                                        return (
                                            <Button
                                                key={`${action.type}-${action.label}`}
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link href={href}>
                                                    {action.label}
                                                </Link>
                                            </Button>
                                        );
                                    })}
                                </div>
                            ) : null}
                        </div>
                    ) : null}
                    <OrderStatusTimeline timeline={order.customer_timeline} />
                </section>

                <section className="space-y-3 rounded-xl border border-border bg-surface p-4">
                    <h2 className="font-semibold text-navy">Detalle</h2>
                    <OrderItemsGrouped items={order.items} />
                    <div className="border-t border-border pt-3 text-sm">
                        <p className="text-muted-foreground">
                            Dirección:{' '}
                            {order.delivery_address?.address_text ?? '—'}
                        </p>
                        <p className="text-muted-foreground">
                            Pago: {order.payment_method_label}
                        </p>
                        <p className="mt-2 text-base font-semibold text-navy">
                            Total {formatMoney(order.total)}
                        </p>
                    </div>
                </section>

                {order.can_rate_driver ? (
                    <DriverRatingForm
                        actionUrl={storeRating.url(order.order_number)}
                    />
                ) : null}

                {order.driver_rating ? (
                    <section className="rounded-xl border border-border bg-surface p-4 text-sm">
                        <h2 className="font-semibold text-navy">
                            Tu calificación
                        </h2>
                        <p className="mt-1 text-navy">
                            {'★'.repeat(order.driver_rating.overall_rating)}
                            {'☆'.repeat(5 - order.driver_rating.overall_rating)}
                        </p>
                        {order.driver_rating.comment ? (
                            <p className="mt-1 text-muted-foreground">
                                {order.driver_rating.comment}
                            </p>
                        ) : null}
                    </section>
                ) : null}

                <div className="flex flex-col gap-2">
                    {order.actions.customer_can_cancel ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDialog('cancel')}
                        >
                            Cancelar pedido
                        </Button>
                    ) : null}
                    {order.actions.customer_can_report_problem ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDialog('report')}
                        >
                            Necesito ayuda / Reportar problema
                        </Button>
                    ) : null}
                    <BackButton
                        href={index.url()}
                        label="Volver a mis pedidos"
                    />
                </div>
            </PageContainer>

            <OrderActionDialog
                open={dialog === 'cancel'}
                onOpenChange={(open) => setDialog(open ? 'cancel' : null)}
                title="Cancelar pedido"
                description="Indica el motivo. Esta acción no se puede deshacer."
                actionUrl={cancelOrder.url(order.order_number)}
                codeField="reason_code"
                options={order.actions.customer_cancel_reasons}
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
                description="Un administrador revisará tu reporte. No cancela el pedido."
                actionUrl={storeIncident.url(order.order_number)}
                codeField="type"
                options={order.actions.customer_incident_types}
                selectLabel="Tipo"
                notesLabel="Descripción"
                notesName="description"
                submitLabel="Enviar reporte"
            />
        </>
    );
}
