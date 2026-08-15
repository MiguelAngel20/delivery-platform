import { Form, Head, Link, useForm } from '@inertiajs/react';
import { StatusBadge } from '@/components/data-display/status-badge';
import { FormField } from '@/components/forms/form-field';
import { ContentCard, PageContainer, PageHeader } from '@/components/layout/page';
import { AddressPicker } from '@/components/maps/address-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useAdminCustomOrderEvents } from '@/hooks/realtime/use-order-realtime';
import type { AddressValue } from '@/lib/maps/types';
import { formatMoney } from '@/lib/money';
import admin from '@/routes/admin';
import { claim, index, pickup, quote, reject } from '@/routes/admin/custom-orders';

type QuoteItem = {
    description: string;
    quantity: string;
    unit_price: string;
    subtotal: string;
};

type RequestDetail = {
    id: number;
    status: string;
    status_label: string;
    establishment_name: string | null;
    description: string;
    customer_notes: string | null;
    assigned_admin_user_id: number | null;
    assigned_admin_name: string | null;
    quoted_order_number: string | null;
    merchant_address?: string | null;
    merchant_latitude?: string | null;
    merchant_longitude?: string | null;
    merchant_formatted_address?: string | null;
    merchant_place_id?: string | null;
    merchant_reference?: string | null;
    customer: { name?: string | null };
    delivery?: { address_text: string } | null;
    latest_quote?: {
        total: string;
        subtotal: string;
        service_fee: string;
        items: QuoteItem[];
    } | null;
};

type Props = {
    request: RequestDetail;
    serviceFee: number;
};

export default function AdminCustomOrderShow({
    request,
    serviceFee,
}: Props) {
    useAdminCustomOrderEvents();

    const quoteForm = useForm({
        service_fee: String(serviceFee),
        discount_amount: '0',
        items: [
            { description: '', quantity: '1', unit_price: '', acquisition_cost: '', notes: '' },
        ],
    });

    const rejectForm = useForm({ notes: '' });
    const pickupForm = useForm({
        merchant_address: request.merchant_address ?? '',
        merchant_latitude: request.merchant_latitude ?? '',
        merchant_longitude: request.merchant_longitude ?? '',
        merchant_formatted_address: request.merchant_formatted_address ?? '',
        merchant_place_id: request.merchant_place_id ?? '',
        merchant_reference: request.merchant_reference ?? '',
    });

    return (
        <>
            <Head title={`Solicitud #${request.id}`} />
            <PageContainer>
                <PageHeader
                    title={`Solicitud #${request.id}`}
                    description={request.customer.name ?? 'Cliente'}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={index.url()}>Volver</Link>
                        </Button>
                    }
                />

                <StatusBadge tone="primary">{request.status_label}</StatusBadge>
                {request.assigned_admin_name ? (
                    <p className="mt-2 text-sm text-muted-foreground">
                        Atendida por {request.assigned_admin_name}
                    </p>
                ) : null}

                <div className="mt-4 grid gap-4 lg:grid-cols-2">
                    <ContentCard title="Solicitud">
                        <p className="text-sm">
                            <span className="text-muted-foreground">Establecimiento: </span>
                            {request.establishment_name ?? '—'}
                        </p>
                        <p className="mt-2 whitespace-pre-wrap text-sm">{request.description}</p>
                        {request.customer_notes ? (
                            <p className="mt-2 text-sm text-muted-foreground">
                                Notas: {request.customer_notes}
                            </p>
                        ) : null}
                        <p className="mt-2 text-sm text-muted-foreground">
                            Entrega: {request.delivery?.address_text ?? '—'}
                        </p>
                    </ContentCard>

                    <ContentCard title="Acciones">
                        {request.status === 'pending_review' ||
                        (request.status === 'reviewing' && !request.assigned_admin_user_id) ? (
                            <Form {...claim.form(request.id)}>
                                <Button type="submit">Tomar solicitud</Button>
                            </Form>
                        ) : null}

                        {request.quoted_order_number ? (
                            <p className="text-sm">
                                Pedido {request.quoted_order_number}
                            </p>
                        ) : null}

                        {['pending_review', 'reviewing', 'quoted'].includes(request.status) ? (
                            <form
                                className="mt-4 space-y-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    rejectForm.post(reject.url(request.id));
                                }}
                            >
                                <FormField label="Motivo">
                                    <Textarea
                                        value={rejectForm.data.notes}
                                        onChange={(event) =>
                                            rejectForm.setData('notes', event.target.value)
                                        }
                                    />
                                </FormField>
                                <Button type="submit" variant="outline">
                                    Rechazar solicitud
                                </Button>
                            </form>
                        ) : null}
                    </ContentCard>
                </div>

                {['reviewing', 'quoted', 'pending_review'].includes(request.status) ? (
                    <ContentCard title="Ubicación de recogida" className="mt-4">
                        <AddressPicker
                            value={{
                                address_text: pickupForm.data.merchant_address,
                                formatted_address:
                                    pickupForm.data.merchant_formatted_address,
                                reference: pickupForm.data.merchant_reference,
                                latitude: pickupForm.data.merchant_latitude
                                    ? Number(pickupForm.data.merchant_latitude)
                                    : undefined,
                                longitude: pickupForm.data.merchant_longitude
                                    ? Number(pickupForm.data.merchant_longitude)
                                    : undefined,
                                place_id: pickupForm.data.merchant_place_id,
                            }}
                            onChange={(value: AddressValue) => {
                                pickupForm.setData({
                                    merchant_address: value.address_text,
                                    merchant_latitude: String(value.latitude),
                                    merchant_longitude: String(value.longitude),
                                    merchant_formatted_address:
                                        value.formatted_address ?? '',
                                    merchant_place_id: value.place_id ?? '',
                                    merchant_reference: value.reference ?? '',
                                });
                            }}
                        />
                        <Button
                            type="button"
                            className="mt-3"
                            disabled={
                                pickupForm.processing ||
                                !pickupForm.data.merchant_latitude
                            }
                            onClick={() =>
                                pickupForm.post(pickup.url(request.id), {
                                    preserveScroll: true,
                                })
                            }
                        >
                            Guardar recogida
                        </Button>
                    </ContentCard>
                ) : null}

                {request.latest_quote ? (
                    <ContentCard title="Cotización actual" className="mt-4">
                        <ul className="space-y-1 text-sm">
                            {request.latest_quote.items.map((item, index) => (
                                <li key={`${item.description}-${index}`} className="flex justify-between">
                                    <span>
                                        {item.quantity} × {item.description}
                                    </span>
                                    <span>{formatMoney(item.subtotal)}</span>
                                </li>
                            ))}
                        </ul>
                        <p className="mt-3 font-semibold">
                            Total {formatMoney(request.latest_quote.total)}
                        </p>
                    </ContentCard>
                ) : null}

                {['reviewing', 'quoted'].includes(request.status) ? (
                    <ContentCard title="Nueva cotización" className="mt-4">
                        <form
                            className="space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                quoteForm.post(quote.url(request.id));
                            }}
                        >
                            {quoteForm.data.items.map((item, index) => (
                                <div key={index} className="grid gap-2 md:grid-cols-4">
                                    <Input
                                        placeholder="Producto"
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
                                        min="0.01"
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
                                        min="0"
                                        step="0.01"
                                        placeholder="Precio"
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
                                        min="0"
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
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    quoteForm.setData('items', [
                                        ...quoteForm.data.items,
                                        {
                                            description: '',
                                            quantity: '1',
                                            unit_price: '',
                                            acquisition_cost: '',
                                            notes: '',
                                        },
                                    ])
                                }
                            >
                                + Partida
                            </Button>
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
                            <Button type="submit">Enviar cotización</Button>
                        </form>
                    </ContentCard>
                ) : null}
            </PageContainer>
        </>
    );
}

AdminCustomOrderShow.layout = {
    title: 'Solicitud',
    breadcrumbs: [
        { title: 'Personalizados', href: admin.customOrders.index() },
    ],
};
