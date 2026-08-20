import { Head, Link, router } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import {
    DataTable
} from '@/components/data-display/data-table';
import type {DataTableColumn} from '@/components/data-display/data-table';
import { StatusBadge } from '@/components/data-display/status-badge';
import { FilterSelect } from '@/components/forms/filter-select';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import business from '@/routes/business';
import {
    create,
    edit,
    index,
} from '@/routes/business/categories';

type CategoryRow = {
    id: number;
    branch_id: number;
    branch_name?: string;
    name: string;
    description?: string | null;
    sort_order: number;
    is_active: boolean;
};

type Filters = {
    search: string;
    branch_id: string;
    is_active: string;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
};

type Props = {
    categories: Paginated<CategoryRow>;
    filters: Filters;
    options: CatalogFormOptions;
};

const columns: DataTableColumn<CategoryRow>[] = [
    {
        key: 'name',
        header: 'Categoría',
        cell: (row) => row.name,
    },
    {
        key: 'branch',
        header: 'Sucursal',
        cell: (row) => row.branch_name ?? '—',
    },
    {
        key: 'status',
        header: 'Estado',
        cell: (row) => (
            <StatusBadge tone={row.is_active ? 'success' : 'neutral'}>
                {row.is_active ? 'Activa' : 'Inactiva'}
            </StatusBadge>
        ),
    },
    {
        key: 'actions',
        header: 'Acciones',
        className: 'text-right',
        cell: (row) => (
            <Button variant="ghost" size="icon" className="size-8" asChild>
                <Link
                    href={edit.url(row.id)}
                    aria-label={`Editar ${row.name}`}
                    title="Editar"
                >
                    <Pencil className="size-4" />
                </Link>
            </Button>
        ),
    },
];

function visitFilters(next: Partial<Filters> & { page?: number }) {
    router.get(
        index.url({
            query: next,
        }),
        {},
        { preserveState: true, replace: true },
    );
}

export default function BusinessCategoriesIndex({
    categories,
    filters,
    options,
}: Props) {
    const [search, setSearch] = useState(filters.search);

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
            <Head title="Categorías" />
            <PageContainer>
                <PageHeader
                    title="Categorías"
                    description="Organiza el menú por sucursal."
                    actions={
                        <Button asChild>
                            <Link href={create.url()}>Nueva categoría</Link>
                        </Button>
                    }
                />

                <div className="mb-4 grid gap-3 md:grid-cols-3">
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Buscar categoría..."
                    />
                    <FilterSelect
                        label="Sucursal"
                        value={filters.branch_id || ''}
                        onChange={(event) =>
                            visitFilters({
                                ...filters,
                                branch_id: event.target.value,
                                page: 1,
                            })
                        }
                    >
                        <option value="">Todas las sucursales</option>
                        {options.branches.map((branch) => (
                            <option key={branch.value} value={branch.value}>
                                {branch.label}
                            </option>
                        ))}
                    </FilterSelect>
                    <FilterSelect
                        label="Estado"
                        value={filters.is_active || ''}
                        onChange={(event) =>
                            visitFilters({
                                ...filters,
                                is_active: event.target.value,
                                page: 1,
                            })
                        }
                    >
                        <option value="">Todos los estados</option>
                        <option value="1">Activas</option>
                        <option value="0">Inactivas</option>
                    </FilterSelect>
                </div>

                <DataTable
                    columns={columns}
                    data={categories.data}
                    rowKey={(row) => row.id}
                />

                {categories.last_page > 1 ? (
                    <div className="mt-4 flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={categories.current_page <= 1}
                            onClick={() =>
                                visitFilters({
                                    ...filters,
                                    page: categories.current_page - 1,
                                })
                            }
                        >
                            Anterior
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={
                                categories.current_page >= categories.last_page
                            }
                            onClick={() =>
                                visitFilters({
                                    ...filters,
                                    page: categories.current_page + 1,
                                })
                            }
                        >
                            Siguiente
                        </Button>
                    </div>
                ) : null}
            </PageContainer>
        </>
    );
}

BusinessCategoriesIndex.layout = {
    breadcrumbs: [
        { title: 'Business', href: business.home.url() },
        { title: 'Categorías', href: index.url() },
    ],
};
