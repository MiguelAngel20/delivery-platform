import { Head, Link, router, usePage } from '@inertiajs/react';
import { Eye, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { DataTable } from '@/components/data-display/data-table';
import type { DataTableColumn } from '@/components/data-display/data-table';
import { StatusBadge } from '@/components/data-display/status-badge';
import type { StatusTone } from '@/components/data-display/status-badge';
import { FilterSelect } from '@/components/forms/filter-select';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { canAdmin  } from '@/lib/admin-access';
import type {AdminAccess} from '@/lib/admin-access';
import admin from '@/routes/admin';
import { destroy, index, show } from '@/routes/admin/customers';

type Option = { value: string; label: string };

type CustomerRow = {
    id: number;
    name: string | null;
    email: string | null;
    phone_verified: boolean;
    email_verified: boolean;
    has_email: boolean;
    completed_orders: number;
    cancelled_orders: number;
    trust_level: string;
    trust_level_label: string;
    trust_level_tone: StatusTone;
    trust_score: string | number | null;
    requires_review: boolean;
    has_order_history: boolean;
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

function customerColumns(
    onDelete: (row: CustomerRow) => void,
    canDelete: boolean,
): DataTableColumn<CustomerRow>[] {
    return [
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
        key: 'verification',
        header: 'Verificación',
        cell: (row) => (
            <div className="flex flex-col items-start gap-1">
                <StatusBadge tone={row.phone_verified ? 'success' : 'warning'}>
                    {row.phone_verified
                        ? 'Teléfono verificado'
                        : 'Teléfono pendiente'}
                </StatusBadge>
                <StatusBadge
                    tone={
                        row.email_verified
                            ? 'success'
                            : row.has_email
                              ? 'warning'
                              : 'neutral'
                    }
                >
                    {row.email_verified
                        ? 'Correo verificado'
                        : row.has_email
                          ? 'Correo pendiente'
                          : 'Sin correo'}
                </StatusBadge>
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
            <div className="flex items-center justify-end gap-1">
                <Button variant="ghost" size="icon" asChild>
                    <Link
                        href={show.url(row.id)}
                        aria-label={`Ver ${row.name ?? 'cliente'}`}
                    >
                        <Eye className="size-4" />
                    </Link>
                </Button>
                {row.has_order_history || !canDelete ? null : (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="text-muted-foreground hover:text-destructive"
                        aria-label={`Eliminar ${row.name ?? 'cliente'}`}
                        onClick={() => onDelete(row)}
                    >
                        <Trash2 className="size-4" />
                    </Button>
                )}
            </div>
        ),
    },
    ];
}

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
    const { adminAccess } = usePage().props as {
        adminAccess: AdminAccess | null;
    };
    const [search, setSearch] = useState(filters.search);

    const deleteCustomer = (row: CustomerRow) => {
        const label = row.name ?? row.email ?? 'este cliente';

        if (
            !window.confirm(
                `¿Eliminar a "${label}"? No tiene pedidos. Esta acción no se puede deshacer.`,
            )
        ) {
            return;
        }

        router.delete(destroy.url(row.id));
    };

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
                    columns={customerColumns(
                        deleteCustomer,
                        canAdmin(adminAccess, 'customers', 'delete'),
                    )}
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
