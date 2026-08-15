import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { DataTable } from '@/components/data-display/data-table';
import type { DataTableColumn } from '@/components/data-display/data-table';
import { StatusBadge } from '@/components/data-display/status-badge';
import type { StatusTone } from '@/components/data-display/status-badge';
import { FilterSelect } from '@/components/forms/filter-select';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import admin from '@/routes/admin';
import { index, show } from '@/routes/admin/customers';

type Option = { value: string; label: string };

type CustomerRow = {
    id: number;
    name: string | null;
    email: string | null;
    completed_orders: number;
    cancelled_orders: number;
    trust_level: string;
    trust_level_label: string;
    trust_level_tone: StatusTone;
    trust_score: string | number | null;
    requires_review: boolean;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
};

type Props = {
    customers: Paginated<CustomerRow>;
    filters: {
        search: string;
        trust_level: string;
    };
    trustLevels: Option[];
};

const columns: DataTableColumn<CustomerRow>[] = [
    {
        key: 'name',
        header: 'Cliente',
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
        key: 'cancelled_orders',
        header: 'Cancelaciones',
        cell: (row) => row.cancelled_orders,
    },
    {
        key: 'trust_level',
        header: 'Trust Level',
        cell: (row) => (
            <StatusBadge tone={row.trust_level_tone}>
                {row.trust_level_label}
            </StatusBadge>
        ),
    },
    {
        key: 'trust_score',
        header: 'Trust Score',
        cell: (row) => row.trust_score ?? '—',
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

function visitFilters(
    next: Props['filters'] & { page?: number },
) {
    router.get(
        index.url({
            query: {
                search: next.search || undefined,
                trust_level: next.trust_level || undefined,
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

export default function AdminCustomersIndex({
    customers,
    filters,
    trustLevels,
}: Props) {
    const [search, setSearch] = useState(filters.search);

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
            <Head title="Clientes" />
            <PageContainer>
                <PageHeader title="Clientes" />
                <DataTable
                    columns={columns}
                    data={customers.data}
                    rowKey={(row) => row.id}
                    search={{
                        value: search,
                        onChange: setSearch,
                        placeholder: 'Buscar por nombre, correo o teléfono',
                    }}
                    filters={
                        <FilterSelect
                            label="Trust Level"
                            value={filters.trust_level || ''}
                            onChange={(event) =>
                                visitFilters({
                                    ...filters,
                                    search,
                                    trust_level: event.target.value,
                                })
                            }
                        >
                            <option value="">Todos</option>
                            {trustLevels.map((level) => (
                                <option key={level.value} value={level.value}>
                                    {level.label}
                                </option>
                            ))}
                        </FilterSelect>
                    }
                    pagination={{
                        page: customers.current_page,
                        lastPage: customers.last_page,
                        onPageChange: (page) =>
                            visitFilters({ ...filters, search, page }),
                    }}
                />
            </PageContainer>
        </>
    );
}

AdminCustomersIndex.layout = {
    title: 'Clientes',
    breadcrumbs: [
        {
            title: 'Clientes',
            href: admin.customers.index(),
        },
    ],
};
