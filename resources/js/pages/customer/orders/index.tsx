import { Head, Link } from '@inertiajs/react';
import { StatusBadge } from '@/components/data-display/status-badge';
import { EmptyState } from '@/components/feedback/empty-state';
import { PageContainer } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';
import { show } from '@/routes/customer/orders';

type OrderRow = {
    order_number: string;
    order_status: string;
    order_status_label: string;
    total: string;
    restaurant: { name?: string | null };
    estimated_preparation_minutes?: number | null;
    is_active: boolean;
};

type Paginated<T> = {
    data: T[];
};

type Props = {
    activeOrders: OrderRow[];
    historyOrders: Paginated<OrderRow>;
};

function OrderListCard({ order }: { order: OrderRow }) {
    return (
        <Link
            href={show.url(order.order_number)}
            className="block rounded-xl border border-border bg-surface p-4 shadow-sm"
        >
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h3 className="font-semibold text-navy">
                        #{order.order_number}
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        {order.restaurant.name}
                    </p>
                </div>
                <StatusBadge
                    tone={order.is_active ? 'primary' : 'success'}
                >
                    {order.order_status_label}
                </StatusBadge>
            </div>
            <div className="mt-3 flex items-center justify-between text-sm">
                <span className="text-muted-foreground">
                    {order.estimated_preparation_minutes
                        ? `Est. ${order.estimated_preparation_minutes} min`
                        : '—'}
                </span>
                <span className="font-semibold text-navy">
                    {formatMoney(order.total)}
                </span>
            </div>
        </Link>
    );
}

export default function CustomerOrdersIndex({
    activeOrders,
    historyOrders,
}: Props) {
    return (
        <>
            <Head title="Mis pedidos" />
            <PageContainer className="gap-6 px-4 py-4 md:px-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-navy">
                        Mis pedidos
                    </h1>
                </div>

                <section className="space-y-3">
                    <h2 className="font-semibold text-navy">Activos</h2>
                    {activeOrders.length === 0 ? (
                        <EmptyState title="No hay pedidos activos" />
                    ) : (
                        <div className="space-y-3">
                            {activeOrders.map((order) => (
                                <OrderListCard
                                    key={order.order_number}
                                    order={order}
                                />
                            ))}
                        </div>
                    )}
                </section>

                <section className="space-y-3">
                    <h2 className="font-semibold text-navy">Historial</h2>
                    {historyOrders.data.length === 0 ? (
                        <EmptyState title="No hay pedidos" />
                    ) : (
                        <div className="space-y-3">
                            {historyOrders.data.map((order) => (
                                <OrderListCard
                                    key={order.order_number}
                                    order={order}
                                />
                            ))}
                        </div>
                    )}
                </section>
            </PageContainer>
        </>
    );
}
