import { Form, Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { StatusBadge } from '@/components/data-display/status-badge';
import { FormField } from '@/components/forms/form-field';
import { ContentCard, PageContainer, PageHeader } from '@/components/layout/page';
import { BackButton } from '@/components/navigation/back-button';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useAdminOrderEvents } from '@/hooks/realtime/use-order-realtime';
import { formatMoney } from '@/lib/money';
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
    business_status_label: string;
    is_custom: boolean;
    is_platform_managed: boolean;
    total: string;
    service_fee: string;
    discount_total: string;
    notes?: string | null;
    customer: { name?: string | null; phone?: string | null };
    restaurant: { name?: string | null; branch_name?: string | null };
    items: Array<{
        id: number;
        product_name: string;
        quantity: string;
        unit_final_price: string;
        unit_acquisition_cost?: string | null;
        subtotal: string;
        notes?: string | null;
    }>;
    delivery_address?: { address_text: string } | null;
    pickup_address?: { address_text: string } | null;
    pending_quote?: {
        total: string;
        items: QuoteItem[];
    } | null;
    actions: {
        admin_can_confirm: boolean;
        admin_can_reject: boolean;
        admin_can_cancel: boolean;
    };
};

type Props = {
    order: OrderDetail;
    preparationOptions: number[];
};

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

    return (
        <>
            <Head title={`Pedido ${order.order_number}`} />
            <PageContainer>
                <PageHeader
                    title={`#${order.order_number}`}
                    description={`${order.customer.name ?? 'Cliente'} · ${order.restaurant.name ?? ''}`}
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
                        <StatusBadge tone="info">Administrada por RIDE</StatusBadge>
                    ) : null}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <ContentCard title="Detalle">
                        <ul className="space-y-2 text-sm">
                            {order.items.map((item) => (
                                <li key={item.id} className="flex justify-between gap-3">
                                    <span>
                                        {item.quantity}x {item.product_name}
                                    </span>
                                    <span>{formatMoney(item.subtotal)}</span>
                                </li>
                            ))}
                        </ul>
                        <p className="mt-3 text-sm text-muted-foreground">
                            Recogida: {order.pickup_address?.address_text ?? '—'}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Entrega: {order.delivery_address?.address_text ?? '—'}
                        </p>
                        {order.notes ? (
                            <p className="mt-2 text-sm">{order.notes}</p>
                        ) : null}
                        <p className="mt-3 text-base font-semibold">
                            Total {formatMoney(order.total)}
                        </p>
                    </ContentCard>

                    <ContentCard title="Operación">
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
                                            setMinutes(Number(event.target.value))
                                        }
                                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    >
                                        {preparationOptions.map((option) => (
                                            <option key={option} value={option}>
                                                {option} minutos
                                            </option>
                                        ))}
                                    </select>
                                </FormField>
                                <Button type="submit">Confirmar pedido</Button>
                            </Form>
                        ) : null}

                        {order.order_status === 'preparing' ? (
                            <Form {...ready.form(order.order_number)} className="mt-3">
                                <Button type="submit" variant="outline">
                                    Marcar listo
                                </Button>
                            </Form>
                        ) : null}

                        {order.actions.admin_can_reject ? (
                            <form
                                className="mt-4 space-y-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    rejectForm.post(reject.url(order.order_number));
                                }}
                            >
                                <FormField label="Motivo de rechazo">
                                    <Textarea
                                        value={rejectForm.data.reason}
                                        onChange={(event) =>
                                            rejectForm.setData('reason', event.target.value)
                                        }
                                    />
                                </FormField>
                                <Button type="submit" variant="outline">
                                    Rechazar
                                </Button>
                            </form>
                        ) : null}
                    </ContentCard>
                </div>

                {order.is_platform_managed && order.order_status === 'pending_platform' ? (
                    <ContentCard title="Ajuste de precio" className="mt-4">
                        <form
                            className="space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                quoteForm.post(storeQuote.url(order.order_number));
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
                                            const items = [...quoteForm.data.items];
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
                                            const items = [...quoteForm.data.items];
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
                                            const items = [...quoteForm.data.items];
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
                                            const items = [...quoteForm.data.items];
                                            items[index] = {
                                                ...item,
                                                acquisition_cost: event.target.value,
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
                                        quoteForm.setData('service_fee', event.target.value)
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
