import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import {
    DEFAULT_CATALOG_PER_PAGE,
    PerPageSelect,
} from '@/components/catalog/per-page-select';
import { DataTable } from '@/components/data-display/data-table';
import type { DataTableColumn } from '@/components/data-display/data-table';
import { StatusBadge } from '@/components/data-display/status-badge';
import { FilterSelect } from '@/components/forms/filter-select';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import business from '@/routes/business';
import {
    create,
    destroy,
    edit,
    index,
} from '@/routes/business/subcategories';

type SubcategoryRow = {
    id: number;
    branch_id: number;
    branch_name?: string;
    parent_id?: number | null;
    parent_name?: string | null;
    name: string;
    display_name?: string;
    is_active: boolean;
    can_delete?: boolean;
};

type Filters = {
    search: string;
    branch_id: string;
    parent_id: string;
    is_active: string;
    per_page: number;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
};

type Props = {
    subcategories: Paginated<SubcategoryRow>;
    filters: Filters;
    options: CatalogFormOptions;
};

function visitFilters(next: Partial<Filters> & { page?: number }) {
    router.get(
        index.url({
            query: {
                search: next.search || undefined,
                branch_id: next.branch_id || undefined,
                parent_id: next.parent_id || undefined,
                is_active: next.is_active || undefined,
                per_page: next.per_page || DEFAULT_CATALOG_PER_PAGE,
                page: next.page,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

export default function BusinessSubcategoriesIndex({
    subcategories,
    filters,
    options,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const perPage = filters.per_page || DEFAULT_CATALOG_PER_PAGE;

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            if (search !== filters.search) {
                visitFilters({ ...filters, search, page: 1 });
            }
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [search, filters]);

    const columns = useMemo<DataTableColumn<SubcategoryRow>[]>(
        () => [
            {
                key: 'name',
                header: 'Subcategoría',
                cell: (row) => (
                    <div>
                        <p className="font-medium">{row.name}</p>
                        <p className="text-xs text-muted-foreground">
                            En {row.parent_name ?? '—'}
                        </p>
                    </div>
                ),
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
                    <div className="flex justify-end gap-1">
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            asChild
                        >
                            <Link
                                href={edit.url(row.id)}
                                aria-label={`Editar ${row.name}`}
                                title="Editar"
                            >
                                <Pencil className="size-4" />
                            </Link>
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-8 text-muted-foreground hover:text-destructive disabled:opacity-40"
                            aria-label={`Eliminar ${row.name}`}
                            title={
                                row.can_delete
                                    ? 'Eliminar'
                                    : 'No se puede eliminar: está en uso'
                            }
                            disabled={!row.can_delete}
                            onClick={() => {
                                if (!row.can_delete) {
                                    return;
                                }

                                if (
                                    !window.confirm(
                                        `¿Eliminar la subcategoría "${row.name}"?`,
                                    )
                                ) {
                                    return;
                                }

                                router.delete(destroy.url(row.id));
                            }}
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    </div>
                ),
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Subcategorías" />
            <PageContainer>
                <PageHeader
                    title="Subcategorías"
                    description="Opcional. Sirve para organizar mejor dentro de una categoría principal."
                    actions={
                        <Button asChild>
                            <Link href={create.url()}>Nueva subcategoría</Link>
                        </Button>
                    }
                />

                <div className="mb-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Buscar subcategoría..."
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
                        label="Categoría principal"
                        value={filters.parent_id || ''}
                        onChange={(event) =>
                            visitFilters({
                                ...filters,
                                parent_id: event.target.value,
                                page: 1,
                            })
                        }
                    >
                        <option value="">Todas</option>
                        {(options.parent_categories ?? []).map((parent) => (
                            <option key={parent.value} value={parent.value}>
                                {parent.label}
                            </option>
                        ))}
                    </FilterSelect>
                    <PerPageSelect
                        value={perPage}
                        onChange={(nextPerPage) =>
                            visitFilters({
                                ...filters,
                                per_page: nextPerPage,
                                page: 1,
                            })
                        }
                    />
                </div>

                <DataTable
                    columns={columns}
                    data={subcategories.data}
                    rowKey={(row) => row.id}
                    pagination={{
                        page: subcategories.current_page,
                        lastPage: subcategories.last_page,
                        onPageChange: (page) =>
                            visitFilters({ ...filters, page }),
                    }}
                />
            </PageContainer>
        </>
    );
}

BusinessSubcategoriesIndex.layout = {
    breadcrumbs: [
        { title: 'Business', href: business.home.url() },
        { title: 'Subcategorías', href: index.url() },
    ],
};
