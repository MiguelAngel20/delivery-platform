import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { DataTable } from '@/components/data-display/data-table';
import type { DataTableColumn } from '@/components/data-display/data-table';
import { StatusBadge } from '@/components/data-display/status-badge';
import { FilterSelect } from '@/components/forms/filter-select';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { useAdminOrderEvents } from '@/hooks/realtime/use-order-realtime';
import admin from '@/routes/admin';
import { index, show } from '@/routes/admin/drivers';

type DriverRow = {
    id: number;
    name: string | null;
    email: string | null;
    completed_orders: number;
    accepted_orders: number;
    cancelled_orders: number;
    average_rating: string | number | null;
    total_ratings: number;
    trust_score: string | number | null;
    quality_label?: string | null;
    requires_review: boolean;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
};

type Props = {
    drivers: Paginated<DriverRow>;
    filters: {
        search: string;
        requires_review: string;
    };
};

const columns: DataTableColumn<DriverRow>[] = [
    {
        key: 'name',
        header: 'Repartidor',
        cell: (row) => (
            <div>
                <p className="font-medium text-navy">{row.name ?? '—'}</p>
                <p className="text-xs text-muted-foreground">{row.email}</p>
            </div>
        ),
    },
    {
        key: 'completed_orders',
        header: 'Completados',
        cell: (row) => row.completed_orders,
    },
    {
        key: 'average_rating',
        header: 'Rating',
        cell: (row) =>
            row.average_rating
                ? `${row.average_rating} ★ (${row.total_ratings})`
                : 'Sin calificaciones',
    },
    {
        key: 'trust_score',
        header: 'Trust Score',
        cell: (row) => row.trust_score ?? '—',
    },
    {
        key: 'review',
        header: 'Revisión',
        cell: (row) =>
            row.requires_review ? (
                <StatusBadge tone="warning">Requires Review</StatusBadge>
            ) : (
                <StatusBadge tone="success">
                    {row.quality_label ?? 'OK'}
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

function visitFilters(next: Props['filters'] & { page?: number }) {
    router.get(
        index.url({
            query: {
                search: next.search || undefined,
                requires_review: next.requires_review || undefined,
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

export default function AdminDriversIndex({ drivers, filters }: Props) {
    const [search, setSearch] = useState(filters.search);

    useAdminOrderEvents(true, ['drivers']);

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            if (search === filters.search) {
                return;
            }

            visitFilters({
                ...filters,
                search,
            });
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [search, filters]);

    return (
        <>
            <Head title="Repartidores" />
            <PageContainer>
                <PageHeader title="Repartidores" />
                <DataTable
                    columns={columns}
                    data={drivers.data}
                    rowKey={(row) => row.id}
                    search={{
                        value: search,
                        onChange: setSearch,
                        placeholder: 'Buscar por nombre, correo o teléfono',
                    }}
                    filters={
                        <FilterSelect
                            label="Revisión"
                            value={filters.requires_review || ''}
                            onChange={(event) =>
                                visitFilters({
                                    ...filters,
                                    search,
                                    requires_review: event.target.value,
                                })
                            }
                        >
                            <option value="">Todos</option>
                            <option value="1">Requires Review</option>
                        </FilterSelect>
                    }
                    pagination={{
                        page: drivers.current_page,
                        lastPage: drivers.last_page,
                        onPageChange: (page) =>
                            visitFilters({ ...filters, search, page }),
                    }}
                />
            </PageContainer>
        </>
    );
}

AdminDriversIndex.layout = {
    title: 'Repartidores',
    breadcrumbs: [
        {
            title: 'Repartidores',
            href: admin.drivers.index(),
        },
    ],
};
