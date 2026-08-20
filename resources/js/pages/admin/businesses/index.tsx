import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    businessStatusTone
    
    
    
} from '@/apps/admin/businesses/types';
import type {BusinessFilterOptions, BusinessListItem, Paginated} from '@/apps/admin/businesses/types';
import {
    DataTable
    
} from '@/components/data-display/data-table';
import type {DataTableColumn} from '@/components/data-display/data-table';
import { StatusBadge } from '@/components/data-display/status-badge';
import { FilterSelect } from '@/components/forms/filter-select';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import admin from '@/routes/admin';
import { create, index, show } from '@/routes/admin/businesses';

type Filters = {
    search: string;
    status: string;
    operation_mode: string;
    delivery_mode: string;
};

type Props = {
    businesses: Paginated<BusinessListItem>;
    filters: Filters;
    options: BusinessFilterOptions;
};

const columns: DataTableColumn<BusinessListItem>[] = [
    {
        key: 'name',
        header: 'Empresa',
        cell: (row) => (
            <div>
                <p className="font-medium text-navy">{row.name}</p>
                <p className="text-xs text-muted-foreground">{row.slug}</p>
            </div>
        ),
    },
    {
        key: 'operation_mode',
        header: 'Tipo',
        cell: (row) => row.operation_mode_label,
    },
    {
        key: 'delivery_mode',
        header: 'Modalidad de entrega',
        cell: (row) => row.delivery_mode_label,
    },
    {
        key: 'branches_count',
        header: 'Sucursales',
        cell: (row) => row.branches_count,
    },
    {
        key: 'status',
        header: 'Estado',
        cell: (row) => (
            <StatusBadge tone={businessStatusTone(row.status)}>
                {row.status_label}
            </StatusBadge>
        ),
    },
    {
        key: 'created_at',
        header: 'Fecha de registro',
        cell: (row) => row.created_at ?? '—',
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

function visitFilters(next: Partial<Filters> & { page?: number }) {
    router.get(
        index.url({
            query: {
                search: next.search || undefined,
                status: next.status || undefined,
                operation_mode: next.operation_mode || undefined,
                delivery_mode: next.delivery_mode || undefined,
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

export default function AdminBusinessesIndex({
    businesses,
    filters,
    options,
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
    }, [search, filters.search, filters.status, filters.operation_mode, filters.delivery_mode]);

    return (
        <>
            <Head title="Empresas" />
            <PageContainer>
                <PageHeader
                    title="Empresas"
                    actions={
                        <Button asChild>
                            <Link href={create.url()}>+ Nueva empresa</Link>
                        </Button>
                    }
                />
                <DataTable
                    columns={columns}
                    data={businesses.data}
                    rowKey={(row) => row.id}
                    search={{
                        value: search,
                        onChange: setSearch,
                        placeholder: 'Buscar por nombre, correo o teléfono',
                    }}
                    filters={
                        <>
                            <FilterSelect
                                label="Estado"
                                value={filters.status || ''}
                                onChange={(event) =>
                                    visitFilters({
                                        ...filters,
                                        search,
                                        status: event.target.value,
                                    })
                                }
                            >
                                <option value="">Todos los estados</option>
                                {options.statuses.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </FilterSelect>
                            <FilterSelect
                                label="Tipo"
                                value={filters.operation_mode || ''}
                                onChange={(event) =>
                                    visitFilters({
                                        ...filters,
                                        search,
                                        operation_mode: event.target.value,
                                    })
                                }
                            >
                                <option value="">Todos los tipos</option>
                                {options.operation_modes.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </FilterSelect>
                            <FilterSelect
                                label="Modalidad"
                                value={filters.delivery_mode || ''}
                                onChange={(event) =>
                                    visitFilters({
                                        ...filters,
                                        search,
                                        delivery_mode: event.target.value,
                                    })
                                }
                            >
                                <option value="">Todas las modalidades</option>
                                {options.delivery_modes.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </FilterSelect>
                        </>
                    }
                    pagination={{
                        page: businesses.current_page,
                        lastPage: businesses.last_page,
                        onPageChange: (page) => {
                            visitFilters({
                                ...filters,
                                search,
                                page,
                            });
                        },
                    }}
                    emptyTitle="Sin empresas"
                    emptyDescription="Crea la primera empresa o ajusta los filtros."
                />
            </PageContainer>
        </>
    );
}

AdminBusinessesIndex.layout = {
    title: 'Empresas',
    breadcrumbs: [
        {
            title: 'Empresas',
            href: admin.businesses.index(),
        },
    ],
};
