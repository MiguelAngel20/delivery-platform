import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { StatusBadge } from '@/components/data-display/status-badge';
import { FilterSelect } from '@/components/forms/filter-select';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useBusinessOrderEvents } from '@/hooks/realtime/use-order-realtime';
import { formatMoney } from '@/lib/money';
import business from '@/routes/business';
import { index, show } from '@/routes/business/orders';
import type { Auth } from '@/types';

type OrderRow = {
    order_number: string;
    order_status: string;
    business_status_label: string;
    total: string;
    created_at: string | null;
    customer: { name?: string | null };
    restaurant: { branch_name?: string | null };
    items: Array<{
        product_name: string;
        quantity: string;
        options: Array<{ display: string }>;
        notes?: string | null;
    }>;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
};

type Props = {
    orders: Paginated<OrderRow>;
    newCount: number;
    filters: { search: string; status: string };
    statusOptions: Array<{ value: string; label: string }>;
};

function visitFilters(next: Partial<Props['filters']> & { page?: number }) {
    router.get(index.url({ query: next }), {}, { preserveState: true, replace: true });
}

export default function BusinessOrdersIndex({
    orders,
    newCount,
    filters,
    statusOptions,
}: Props) {
    const { realtime } = usePage().props as {
        auth: Auth;
        realtime?: {
            business_id?: number | null;
            branch_ids?: number[];
        };
    };
    const [search, setSearch] = useState(filters.search);

    useBusinessOrderEvents({
        businessId: realtime?.business_id,
        branchIds: realtime?.branch_ids ?? [],
        only: ['orders', 'newCount'],
    });

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            if (search !== filters.search) {
                visitFilters({ ...filters, search, page: 1 });
            }
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [search, filters]);

    return (
        <>
            <Head title="Pedidos" />
            <PageContainer>
                <PageHeader
                    title="Pedidos"
                    description={
                        newCount > 0
                            ? `${newCount} nuevo${newCount === 1 ? '' : 's'}`
                            : 'Comandas de tus sucursales'
                    }
                />

                <div className="mb-4 grid gap-3 md:grid-cols-2">
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Buscar pedido o cliente..."
                    />
                    <FilterSelect
                        label="Estado"
                        value={filters.status || ''}
                        onChange={(event) =>
                            visitFilters({
                                ...filters,
                                status: event.target.value,
                                page: 1,
                            })
                        }
                    >
                        <option value="">Todos</option>
                        {statusOptions.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </FilterSelect>
                </div>

                <div className="space-y-3">
                    {orders.data.map((order) => (
                        <article
                            key={order.order_number}
                            className="rounded-xl border border-border bg-white p-4 shadow-sm"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="font-semibold text-navy">
                                            #{order.order_number}
                                        </h2>
                                        <StatusBadge
                                            tone={
                                                order.order_status ===
                                                'pending_business'
                                                    ? 'info'
                                                    : order.order_status ===
                                                        'preparing'
                                                      ? 'warning'
                                                      : order.order_status ===
                                                          'ready_for_pickup'
                                                        ? 'primary'
                                                        : 'neutral'
                                            }
                                        >
                                            {order.business_status_label}
                                        </StatusBadge>
                                    </div>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {order.customer.name} ·{' '}
                                        {order.restaurant.branch_name}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className="font-semibold text-navy">
                                        {formatMoney(order.total)}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {order.created_at
                                            ? new Date(
                                                  order.created_at,
                                              ).toLocaleString()
                                            : ''}
                                    </p>
                                </div>
                            </div>

                            <ul className="mt-3 space-y-2 text-sm">
                                {order.items.map((item, index) => (
                                    <li key={`${order.order_number}-${index}`}>
                                        <p className="text-navy">
                                            {item.quantity}x {item.product_name}
                                        </p>
                                        {item.options.length > 0 ? (
                                            <p className="text-xs text-muted-foreground">
                                                {item.options
                                                    .map((option) => option.display)
                                                    .join(' · ')}
                                            </p>
                                        ) : null}
                                        {item.notes ? (
                                            <p className="text-xs text-muted-foreground">
                                                Nota: {item.notes}
                                            </p>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>

                            <div className="mt-4 flex justify-end">
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={show.url(order.order_number)}>
                                        Ver / Gestionar
                                    </Link>
                                </Button>
                            </div>
                        </article>
                    ))}
                </div>
            </PageContainer>
        </>
    );
}

BusinessOrdersIndex.layout = {
    breadcrumbs: [
        { title: 'Business', href: business.home.url() },
        { title: 'Pedidos', href: index.url() },
    ],
};
