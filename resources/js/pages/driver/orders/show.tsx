import { Head } from '@inertiajs/react';
import { StatusBadge } from '@/components/data-display/status-badge';
import { ContentCard, PageContainer } from '@/components/layout/page';
import { BackButton } from '@/components/navigation/back-button';
import { formatMoney } from '@/lib/money';
import { formatOrderItemLine } from '@/lib/order-item-line';
import { resolveRestaurantLabel } from '@/lib/restaurant-label';
import { home } from '@/routes/driver';

type OrderDetail = {
    id: number;
    order_number: string;
    business_status_label: string;
    is_custom?: boolean;
    delivered_at: string | null;
    restaurant: {
        name?: string | null;
        branch_name?: string | null;
    };
    customer: {
        name?: string | null;
        public_label?: string | null;
    };
    pickup_address?: {
        address_text: string;
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
        quantity: string;
        subtotal: string;
        notes?: string | null;
        line_label?: string;
        options: Array<{ display: string }>;
    }>;
    notes?: string | null;
    driver_earning: string;
    service_fee: string;
    rating?: {
        overall_rating: number;
        comment?: string | null;
    } | null;
};

type Props = {
    order: OrderDetail;
};

function formatDeliveredAt(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('es-MX', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

export default function DriverOrderShow({ order }: Props) {
    return (
        <>
            <Head title={`Pedido ${order.order_number}`} />
            <PageContainer className="gap-4 px-4 py-4 md:px-6">
                <BackButton href={home()} />

                <div className="space-y-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="text-2xl font-semibold text-navy">
                            #{order.order_number}
                        </h1>
                        <StatusBadge tone="success">
                            {order.business_status_label}
                        </StatusBadge>
                    </div>
                    <p className="text-sm break-words text-muted-foreground">
                        {resolveRestaurantLabel(
                            order.restaurant.name,
                            order.restaurant.branch_name,
                        )}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Entregado: {formatDeliveredAt(order.delivered_at)}
                    </p>
                </div>

                <ContentCard title="Resumen">
                    <dl className="grid gap-3 text-sm">
                        <div className="flex items-start justify-between gap-3">
                            <dt className="text-muted-foreground">Cliente</dt>
                            <dd className="text-right font-medium text-navy">
                                {order.customer.name ?? 'Cliente'}
                                {order.customer.public_label
                                    ? ` · ${order.customer.public_label}`
                                    : ''}
                            </dd>
                        </div>
                        <div className="flex items-start justify-between gap-3">
                            <dt className="text-muted-foreground">
                                Tarifa de servicio
                            </dt>
                            <dd className="font-medium text-navy">
                                {formatMoney(order.service_fee)}
                            </dd>
                        </div>
                        <div className="flex items-start justify-between gap-3">
                            <dt className="text-muted-foreground">
                                Tu ganancia
                            </dt>
                            <dd className="font-semibold text-success">
                                {formatMoney(order.driver_earning)}
                            </dd>
                        </div>
                    </dl>
                </ContentCard>

                {order.pickup_address ? (
                    <ContentCard title="Recogida">
                        <p className="text-sm text-navy">
                            {order.pickup_address.address_text}
                        </p>
                        {order.pickup_address.google_maps_url ? (
                            <a
                                href={order.pickup_address.google_maps_url}
                                target="_blank"
                                rel="noreferrer"
                                className="mt-2 inline-block text-sm text-primary hover:underline"
                            >
                                Abrir en mapas
                            </a>
                        ) : null}
                    </ContentCard>
                ) : null}

                {order.delivery_address ? (
                    <ContentCard title="Entrega">
                        <p className="text-sm text-navy">
                            {order.delivery_address.address_text}
                        </p>
                        {order.delivery_address.reference ? (
                            <p className="mt-1 text-sm text-muted-foreground">
                                Referencia: {order.delivery_address.reference}
                            </p>
                        ) : null}
                        {order.delivery_address.google_maps_url ? (
                            <a
                                href={order.delivery_address.google_maps_url}
                                target="_blank"
                                rel="noreferrer"
                                className="mt-2 inline-block text-sm text-primary hover:underline"
                            >
                                Abrir en mapas
                            </a>
                        ) : null}
                    </ContentCard>
                ) : null}

                <ContentCard title="Productos" bodyClassName="p-0">
                    <ul className="divide-y divide-border">
                        {order.items.map((item) => (
                            <li
                                key={item.id}
                                className="space-y-1 px-4 py-3 md:px-5"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <p className="font-medium text-navy">
                                        {formatOrderItemLine(item)}
                                    </p>
                                    <span className="shrink-0 text-sm font-medium text-navy">
                                        {formatMoney(item.subtotal)}
                                    </span>
                                </div>
                                {item.notes ? (
                                    <p className="text-sm text-muted-foreground">
                                        Notas: {item.notes}
                                    </p>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                </ContentCard>

                {order.notes ? (
                    <ContentCard title="Notas del pedido">
                        <p className="text-sm text-muted-foreground">
                            {order.notes}
                        </p>
                    </ContentCard>
                ) : null}

                {order.rating ? (
                    <ContentCard title="Calificación del cliente">
                        <p className="text-lg font-semibold text-navy">
                            {order.rating.overall_rating} ★
                        </p>
                        {order.rating.comment ? (
                            <p className="mt-2 text-sm text-muted-foreground">
                                “{order.rating.comment}”
                            </p>
                        ) : (
                            <p className="mt-2 text-sm italic text-muted-foreground">
                                Sin comentario
                            </p>
                        )}
                    </ContentCard>
                ) : null}
            </PageContainer>
        </>
    );
}
