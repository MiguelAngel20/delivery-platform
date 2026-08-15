import { Head, Link, router } from '@inertiajs/react';
import { DataTable } from '@/components/data-display/data-table';
import type { DataTableColumn } from '@/components/data-display/data-table';
import { StatusBadge } from '@/components/data-display/status-badge';
import type { StatusTone } from '@/components/data-display/status-badge';
import { FilterSelect } from '@/components/forms/filter-select';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { useAdminCustomOrderEvents } from '@/hooks/realtime/use-order-realtime';
import admin from '@/routes/admin';
import { index, show } from '@/routes/admin/custom-orders';

type Option = { value: string; label: string };

type RequestRow = {
    id: number;
    status: string;
    status_label: string;
    establishment_name: string | null;
    description: string;
    customer: { name?: string | null };
    assigned_admin_name: string | null;
    created_at: string | null;
    latest_total: string | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
};

type Props = {
    requests: Paginated<RequestRow>;
    filters: { status: string };
    statusOptions: Option[];
    queue: {
        pending_review: number;
        reviewing: number;
        quoted: number;
    };
};

const statusTone: Record<string, StatusTone> = {
    pending_review: 'warning',
    reviewing: 'info',
    quoted: 'primary',
    converted_to_order: 'success',
    rejected: 'danger',
    cancelled: 'neutral',
};

function visitFilters(next: { status: string; page?: number }) {
    router.get(
        index.url({
            query: {
                status: next.status || undefined,
                page: next.page,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const columns: DataTableColumn<RequestRow>[] = [
    {
        key: 'id',
        header: 'Solicitud',
        cell: (row) => `#${row.id}`,
    },
    {
        key: 'customer',
        header: 'Cliente',
        cell: (row) => row.customer.name ?? '—',
    },
    {
        key: 'establishment',
        header: 'Establecimiento',
        cell: (row) => row.establishment_name ?? '—',
    },
    {
        key: 'description',
        header: 'Descripción',
        cell: (row) => (
            <span className="line-clamp-2 max-w-xs">{row.description}</span>
        ),
    },
    {
        key: 'status',
        header: 'Estado',
        cell: (row) => (
            <StatusBadge tone={statusTone[row.status] ?? 'neutral'}>
                {row.status_label}
            </StatusBadge>
        ),
    },
    {
        key: 'actions',
        header: 'Acciones',
        className: 'text-right',
        cell: (row) => (
            <Button variant="ghost" size="sm" asChild>
                <Link href={show.url(row.id)}>Ver</Link>
            </Button>
        ),
    },
];

export default function AdminCustomOrdersIndex({
    requests,
    filters,
    statusOptions,
    queue,
}: Props) {
    useAdminCustomOrderEvents();

    return (
        <>
            <Head title="Pedidos personalizados" />
            <PageContainer>
                <PageHeader
                    title="Pedidos personalizados"
                    description={`${queue.pending_review} por revisar · ${queue.reviewing} en atención · ${queue.quoted} cotizadas`}
                />
                <DataTable
                    columns={columns}
                    data={requests.data}
                    rowKey={(row) => row.id}
                    filters={
                        <FilterSelect
                            label="Estado"
                            value={filters.status || ''}
                            onChange={(event) =>
                                visitFilters({ status: event.target.value })
                            }
                        >
                            <option value="">Todos</option>
                            {statusOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </FilterSelect>
                    }
                    pagination={{
                        page: requests.current_page,
                        lastPage: requests.last_page,
                        onPageChange: (page) =>
                            visitFilters({ ...filters, page }),
                    }}
                />
            </PageContainer>
        </>
    );
}

AdminCustomOrdersIndex.layout = {
    title: 'Personalizados',
    breadcrumbs: [
        { title: 'Personalizados', href: admin.customOrders.index() },
    ],
};
