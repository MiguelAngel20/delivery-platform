import { Form, Head, useForm } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { StatusBadge } from '@/components/data-display/status-badge';
import { FormField } from '@/components/forms/form-field';
import { ContentCard, PageContainer, PageHeader } from '@/components/layout/page';
import { BackButton } from '@/components/navigation/back-button';
import { OrderCustomerPanel } from '@/components/orders/order-customer-panel';
import { OrderDetailPanel } from '@/components/orders/order-detail-panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useAdminOrderEvents } from '@/hooks/realtime/use-order-realtime';
import { formatMoney } from '@/lib/money';
import { resolveRestaurantLabel } from '@/lib/restaurant-label';
import admin from '@/routes/admin';
import { confirm, ready, reject } from '@/routes/admin/orders';
import { store as storeQuote } from '@/routes/admin/orders/quotes';
import { index } from '@/routes/admin/orders';

type QuoteItem = {
    description: string;
    quantity: string;
    unit_price: string;
    subtotal: string;
    acquisition_cost?: string | null;
};

type OrderDetail = {
    order_number: string;
    order_status: string;
    order_status_label?: string;
    business_status_label: string;
    is_custom: boolean;
    is_platform_managed: boolean;
    total: string;
    service_fee: string;
    discount_total: string;
    notes?: string | null;
    estimated_preparation_minutes?: number | null;
    customer: { name?: string | null; phone?: string | null };
    restaurant: {
        name?: string | null;
        branch_name?: string | null;
        phone?: string | null;
    };
    driver?: { name?: string | null; phone?: string | null } | null;
    items: Array<{
        id: number;
        product_name: string;
        display_name?: string | null;
        category_name?: string | null;
        subcategory_name?: string | null;
        quantity: string;
        unit_final_price: string;
        unit_acquisition_cost?: string | null;
        subtotal: string;
        notes?: string | null;
        line_label?: string;
        options?: Array<{ display: string }>;
    }>;
    delivery_address?: {
        address_text: string;
        reference?: string | null;
        google_maps_url?: string | null;
    } | null;
    pickup_address?: {
        address_text: string;
        google_maps_url?: string | null;
    } | null;
    pending_quote?: {
        total: string;
        items: QuoteItem[];
    } | null;
    actions: {
        admin_can_confirm: boolean;
        admin_can_reject: boolean;
        admin_can_cancel: boolean;
        admin_can_mark_ready?: boolean;
    };
};

type Props = {
    order: OrderDetail;
    preparationOptions: number[];
};

function DetailRow({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-medium text-foreground sm:text-right">
                {children}
            </dd>
        </div>
    );
}

export default function AdminOrderShow({ order, preparationOptions }: Props) {
    const [minutes, setMinutes] = useState(25);
    const rejectForm = useForm({ reason: '' });
    const quoteForm = useForm({
        service_fee: order.service_fee,
        discount_amount: order.discount_total,
        items: order.items.map((item) => ({
            description: item.product_name,
            quantity: item.quantity,
            unit_price: item.unit_final_price,
            acquisition_cost: item.unit_acquisition_cost ?? '',
            notes: item.notes ?? '',
        })),
    });

    useAdminOrderEvents(true, ['order']);

    const hasActions =
        order.actions.admin_can_confirm ||
        order.actions.admin_can_mark_ready ||
        order.actions.admin_can_reject;

    const restaurantLabel = resolveRestaurantLabel(
        order.restaurant.name,
        order.restaurant.branch_name,
        order.is_custom ? 'Pedido personalizado' : 'Negocio',
    );

    return (
        <>
            <Head title={`Pedido ${order.order_number}`} />
            <PageContainer>
                <PageHeader
                    title={`#${order.order_number}`}
                    actions={<BackButton href={index.url()} />}
                />

                <div className="mb-4 flex flex-wrap gap-2">
                    <StatusBadge tone="primary">
                        {order.business_status_label}
                    </StatusBadge>
                    {order.is_custom ? (
                        <StatusBadge tone="neutral">Personalizado</StatusBadge>
                    ) : null}
                    {order.is_platform_managed ? (
                        <StatusBadge tone="info">
                            Administrada por ChisDrive
                        </StatusBadge>
                    ) : null}
                </div>

                <div className="grid min-w-0 items-start gap-4 lg:grid-cols-2">
                    <OrderDetailPanel
                        orderNumber={order.order_number}
                        businessName={restaurantLabel}
                        businessPhone={order.restaurant.phone}
                        items={order.items}
                        pickupAddress={order.pickup_address}
                        notes={order.notes}
                        footer={
                            <p className="border-t border-border pt-3 text-base font-semibold">
                                Total {formatMoney(order.total)}
                            </p>
                        }
                    />

                    <div className="space-y-4">
                        <ContentCard>
                            <OrderCustomerPanel
                                customer={order.customer}
                                deliveryAddress={order.delivery_address}
                                orderNumber={order.order_number}
                            />
                        </ContentCard>

                        <ContentCard title="Operación">
                            <dl className="space-y-3 text-sm">
                                <DetailRow label="Estado">
                                    <StatusBadge tone="primary">
                                        {order.business_status_label}
                                    </StatusBadge>
                                </DetailRow>
                                <DetailRow label="Tiempo al cliente">
                                    {order.estimated_preparation_minutes != null
                                        ? `${order.estimated_preparation_minutes} minutos`
                                        : 'Aún no asignado'}
                                </DetailRow>
                                <DetailRow label="Repartidor">
                                    {order.driver?.name ?? 'Sin asignar'}
                                </DetailRow>
                                {order.driver?.phone ? (
                                    <DetailRow label="Tel. repartidor">
                                        {order.driver.phone}
                                    </DetailRow>
                                ) : null}
                            </dl>

                            {hasActions ? (
                                <div className="mt-4 space-y-3 border-t border-border pt-4">
                                    {order.actions.admin_can_confirm ? (
                                        <Form
                                            {...confirm.form(order.order_number)}
                                            className="space-y-3"
                                        >
                                            <FormField label="Tiempo estimado (min)">
                                                <select
                                                    name="estimated_preparation_minutes"
                                                    value={minutes}
                                                    onChange={(event) =>
                                                        setMinutes(
                                                            Number(
                                                                event.target
                                                                    .value,
                                                            ),
                                                        )
                                                    }
                                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                                >
                                                    {preparationOptions.map(
                                                        (option) => (
                                                            <option
                                                                key={option}
                                                                value={option}
                                                            >
                                                                {option} minutos
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </FormField>
                                            <Button type="submit">
                                                Confirmar pedido
                                            </Button>
                                        </Form>
                                    ) : null}

                                    {order.actions.admin_can_mark_ready ? (
                                        <Form
                                            {...ready.form(order.order_number)}
                                        >
                                            <Button type="submit">
                                                Marcar listo para recoger
                                            </Button>
                                        </Form>
                                    ) : null}

                                    {order.actions.admin_can_reject ? (
                                        <form
                                            className="space-y-3"
                                            onSubmit={(event) => {
                                                event.preventDefault();
                                                rejectForm.post(
                                                    reject.url(
                                                        order.order_number,
                                                    ),
                                                );
                                            }}
                                        >
                                            <FormField label="Motivo de rechazo">
                                                <Textarea
                                                    value={
                                                        rejectForm.data.reason
                                                    }
                                                    onChange={(event) =>
                                                        rejectForm.setData(
                                                            'reason',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </FormField>
                                            <Button
                                                type="submit"
                                                variant="outline"
                                            >
                                                Rechazar
                                            </Button>
                                        </form>
                                    ) : null}
                                </div>
                            ) : null}
                        </ContentCard>
                    </div>
                </div>

                {order.is_platform_managed &&
                order.order_status === 'pending_platform' ? (
                    <ContentCard title="Ajuste de precio" className="mt-4">
                        <form
                            className="space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                quoteForm.post(
                                    storeQuote.url(order.order_number),
                                );
                            }}
                        >
                            {quoteForm.data.items.map((item, index) => (
                                <div
                                    key={`${item.description}-${index}`}
                                    className="grid gap-2 md:grid-cols-4"
                                >
                                    <Input
                                        value={item.description}
                                        onChange={(event) => {
                                            const items = [
                                                ...quoteForm.data.items,
                                            ];
                                            items[index] = {
                                                ...item,
                                                description: event.target.value,
                                            };
                                            quoteForm.setData('items', items);
                                        }}
                                    />
                                    <Input
                                        type="number"
                                        step="0.01"
                                        value={item.quantity}
                                        onChange={(event) => {
                                            const items = [
                                                ...quoteForm.data.items,
                                            ];
                                            items[index] = {
                                                ...item,
                                                quantity: event.target.value,
                                            };
                                            quoteForm.setData('items', items);
                                        }}
                                    />
                                    <Input
                                        type="number"
                                        step="0.01"
                                        value={item.unit_price}
                                        onChange={(event) => {
                                            const items = [
                                                ...quoteForm.data.items,
                                            ];
                                            items[index] = {
                                                ...item,
                                                unit_price: event.target.value,
                                            };
                                            quoteForm.setData('items', items);
                                        }}
                                    />
                                    <Input
                                        type="number"
                                        step="0.01"
                                        placeholder="Costo"
                                        value={item.acquisition_cost}
                                        onChange={(event) => {
                                            const items = [
                                                ...quoteForm.data.items,
                                            ];
                                            items[index] = {
                                                ...item,
                                                acquisition_cost:
                                                    event.target.value,
                                            };
                                            quoteForm.setData('items', items);
                                        }}
                                    />
                                </div>
                            ))}
                            <FormField label="Servicio">
                                <Input
                                    type="number"
                                    step="0.01"
                                    value={quoteForm.data.service_fee}
                                    onChange={(event) =>
                                        quoteForm.setData(
                                            'service_fee',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <Button type="submit">Proponer nuevo total</Button>
                        </form>
                    </ContentCard>
                ) : null}
            </PageContainer>
        </>
    );
}

AdminOrderShow.layout = {
    title: 'Pedido',
    breadcrumbs: [
        { title: 'Pedidos', href: admin.orders.index() },
    ],
};
