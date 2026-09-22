import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { DataTable } from '@/components/data-display/data-table';
import type { DataTableColumn } from '@/components/data-display/data-table';
import { StatusBadge } from '@/components/data-display/status-badge';
import type { StatusTone } from '@/components/data-display/status-badge';
import { EmptyState } from '@/components/feedback/empty-state';
import { FilterSelect } from '@/components/forms/filter-select';
import { SearchInput } from '@/components/forms/search-input';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { Pagination } from '@/components/ui/pagination';
import { useAdminOrderEvents } from '@/hooks/realtime/use-order-realtime';
import { formatMoney } from '@/lib/money';
import admin from '@/routes/admin';
import { index, show } from '@/routes/admin/orders';

type Option = { value: string; label: string };

type OrderRow = {
    id: number;
    order_number: string;
    order_status: string;
    business_status_label: string;
    total: string;
    created_at: string | null;
    is_custom: boolean;
    is_platform_managed: boolean;
    items_summary?: string;
    restaurant: { name?: string | null };
    customer: { name?: string | null };
    driver: { name?: string | null } | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
};

type Props = {
    orders: Paginated<OrderRow>;
    filters: {
        search: string;
        filter: string;
    };
    filterOptions: Option[];
    queue: {
        pending_platform: number;
        pending_customer_confirmation: number;
        open_incidents: number;
    };
};

const statusTone: Record<string, StatusTone> = {
    pending_business: 'info',
    pending_platform: 'warning',
    pending_customer_confirmation: 'warning',
    preparing: 'primary',
    ready_for_pickup: 'info',
    driver_assigned: 'info',
    on_the_way: 'info',
    delivered: 'success',
    cancelled: 'danger',
    rejected: 'danger',
};

function visitFilters(next: Props['filters'] & { page?: number }) {
    router.get(
        index.url({
            query: {
                search: next.search || undefined,
                filter: next.filter || undefined,
                page: next.page,
            },
        }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function OrderBadges({ row }: { row: OrderRow }) {
    if (row.is_custom) {
        return <StatusBadge tone="neutral">Personalizado</StatusBadge>;
    }

    if (row.is_platform_managed) {
        return <StatusBadge tone="info">ChisDrive</StatusBadge>;
    }

    return null;
}

const columns: DataTableColumn<OrderRow>[] = [
    {
        key: 'order_number',
        header: 'Pedido',
        cell: (row) => (
            <div>
                <p className="font-medium text-navy">{row.order_number}</p>
                <OrderBadges row={row} />
            </div>
        ),
    },
    {
        key: 'customer',
        header: 'Cliente',
        cell: (row) => row.customer.name ?? '—',
    },
    {
        key: 'business',
        header: 'Establecimiento',
        cell: (row) => row.restaurant.name ?? '—',
    },
    {
        key: 'driver',
        header: 'Repartidor',
        cell: (row) => row.driver?.name ?? '—',
    },
    {
        key: 'status',
        header: 'Estado',
        cell: (row) => (
            <StatusBadge tone={statusTone[row.order_status] ?? 'neutral'}>
                {row.business_status_label}
            </StatusBadge>
        ),
    },
    {
        key: 'total',
        header: 'Total',
        cell: (row) => formatMoney(row.total),
    },
    {
        key: 'actions',
        header: 'Acciones',
        className: 'text-right',
        cell: (row) => (
            <Button variant="ghost" size="sm" asChild>
                <Link href={show.url(row.order_number)}>Ver</Link>
            </Button>
        ),
    },
];

function OrderCard({ row }: { row: OrderRow }) {
    const isPending = [
        'pending_business',
        'pending_platform',
        'pending_customer_confirmation',
    ].includes(row.order_status);

    return (
        <article className="rounded-xl border border-border bg-surface p-4 shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="font-semibold text-navy">
                            #{row.order_number}
                        </h2>
                        <StatusBadge
                            tone={statusTone[row.order_status] ?? 'neutral'}
                        >
                            {row.business_status_label}
                        </StatusBadge>
                        <OrderBadges row={row} />
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {row.customer.name ?? 'Cliente'}
                        {row.restaurant.name
                            ? ` · ${row.restaurant.name}`
                            : ''}
                    </p>
                    {row.driver?.name ? (
                        <p className="text-sm text-muted-foreground">
                            Repartidor: {row.driver.name}
                        </p>
                    ) : null}
                    {row.items_summary ? (
                        <p className="line-clamp-2 text-sm text-foreground">
                            {row.items_summary}
                        </p>
                    ) : null}
                </div>
                <div className="text-right">
                    <p className="font-semibold text-foreground">
                        {formatMoney(row.total)}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {row.created_at
                            ? new Date(row.created_at).toLocaleString()
                            : ''}
                    </p>
                </div>
            </div>
            <div className="mt-4 flex justify-end">
                <Button
                    variant={isPending ? 'default' : 'outline'}
                    size="sm"
                    asChild
                >
                    <Link href={show.url(row.order_number)}>
                        {isPending ? 'Aceptar / Gestionar' : 'Ver / Gestionar'}
                    </Link>
                </Button>
            </div>
        </article>
    );
}

export default function AdminOrdersIndex({
    orders,
    filters,
    filterOptions,
    queue,
}: Props) {
    const [search, setSearch] = useState(filters.search);

    useAdminOrderEvents(true, ['orders', 'queue']);

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            if (search === filters.search) {
                return;
            }

            visitFilters({ ...filters, search });
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [search, filters]);

    return (
        <>
            <Head title="Pedidos" />
            <PageContainer>
                <PageHeader
                    title="Pedidos"
                    description={`${queue.pending_platform} pendientes ChisDrive · ${queue.pending_customer_confirmation} esperando cliente · ${queue.open_incidents} incidencias`}
                />

                <div className="flex flex-col gap-4">
                    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div className="flex flex-1 flex-col gap-3 md:flex-row md:items-center">
                            <div className="w-full md:max-w-xs">
                                <SearchInput
                                    value={search}
                                    placeholder="Buscar pedido, cliente o empresa"
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                />
                            </div>
                            <FilterSelect
                                label="Filtro"
                                value={filters.filter || ''}
                                onChange={(event) =>
                                    visitFilters({
                                        ...filters,
                                        search,
                                        filter: event.target.value,
                                    })
                                }
                            >
                                {filterOptions.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </FilterSelect>
                        </div>
                    </div>

                    {orders.data.length === 0 ? (
                        <EmptyState
                            title="Sin resultados"
                            description="No hay pedidos con estos filtros."
                        />
                    ) : (
                        <>
                            <div className="space-y-3 lg:hidden">
                                {orders.data.map((row) => (
                                    <OrderCard key={row.id} row={row} />
                                ))}
                            </div>

                            <div className="hidden lg:block">
                                <DataTable
                                    columns={columns}
                                    data={orders.data}
                                    rowKey={(row) => row.id}
                                />
                            </div>
                        </>
                    )}

                    {orders.data.length > 0 ? (
                        <Pagination
                            page={orders.current_page}
                            lastPage={orders.last_page}
                            onPageChange={(page) =>
                                visitFilters({ ...filters, search, page })
                            }
                        />
                    ) : null}
                </div>
            </PageContainer>
        </>
    );
}

AdminOrdersIndex.layout = {
    title: 'Pedidos',
    breadcrumbs: [
        {
            title: 'Pedidos',
            href: admin.orders.index(),
        },
    ],
};
