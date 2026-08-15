import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { DataTable } from '@/components/data-display/data-table';
import type { DataTableColumn } from '@/components/data-display/data-table';
import { StatusBadge } from '@/components/data-display/status-badge';
import type { StatusTone } from '@/components/data-display/status-badge';
import { FilterSelect } from '@/components/forms/filter-select';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
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

const columns: DataTableColumn<OrderRow>[] = [
    {
        key: 'order_number',
        header: 'Pedido',
        cell: (row) => (
            <div>
                <p className="font-medium text-navy">{row.order_number}</p>
                {row.is_custom ? (
                    <StatusBadge tone="neutral">Personalizado</StatusBadge>
                ) : row.is_platform_managed ? (
                    <StatusBadge tone="info">RIDE</StatusBadge>
                ) : null}
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
                    description={`${queue.pending_platform} pendientes RIDE · ${queue.pending_customer_confirmation} esperando cliente · ${queue.open_incidents} incidencias`}
                />
                <DataTable
                    columns={columns}
                    data={orders.data}
                    rowKey={(row) => row.id}
                    search={{
                        value: search,
                        onChange: setSearch,
                        placeholder: 'Buscar pedido, cliente o empresa',
                    }}
                    filters={
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
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </FilterSelect>
                    }
                    pagination={{
                        page: orders.current_page,
                        lastPage: orders.last_page,
                        onPageChange: (page) =>
                            visitFilters({ ...filters, search, page }),
                    }}
                />
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
