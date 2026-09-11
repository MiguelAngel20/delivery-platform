import { Head, Link, router } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
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
import { formatMoney } from '@/lib/money';
import business from '@/routes/business';
import { create, edit, index } from '@/routes/business/products';

type ProductRow = {
    id: number;
    branch_id: number;
    branch_name?: string;
    name: string;
    category_name: string;
    list_price: string | null;
    is_available: boolean;
    is_active: boolean;
};

type Filters = {
    search: string;
    branch_id: string;
    category_id: string;
    subcategory_id: string;
    is_available: string;
    is_active: string;
    per_page: number;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
};

type Props = {
    products: Paginated<ProductRow>;
    filters: Filters;
    options: CatalogFormOptions;
};

const columns: DataTableColumn<ProductRow>[] = [
    {
        key: 'name',
        header: 'Producto',
        cell: (row) => (
            <div>
                <p className="font-medium text-foreground">{row.name}</p>
                <p className="text-xs text-muted-foreground">
                    {row.branch_name}
                </p>
            </div>
        ),
    },
    {
        key: 'category',
        header: 'Categoría',
        cell: (row) => row.category_name,
    },
    {
        key: 'price',
        header: 'Precio',
        cell: (row) =>
            row.list_price !== null ? formatMoney(row.list_price) : '—',
    },
    {
        key: 'availability',
        header: 'Disponibilidad',
        cell: (row) => (
            <StatusBadge tone={row.is_available ? 'success' : 'warning'}>
                {row.is_available ? 'Disponible' : 'Agotado'}
            </StatusBadge>
        ),
    },
    {
        key: 'status',
        header: 'Estado',
        cell: (row) => (
            <StatusBadge tone={row.is_active ? 'success' : 'neutral'}>
                {row.is_active ? 'Activo' : 'Inactivo'}
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
            query: {
                search: next.search || undefined,
                branch_id: next.branch_id || undefined,
                category_id: next.category_id || undefined,
                subcategory_id: next.subcategory_id || undefined,
                is_available: next.is_available || undefined,
                is_active: next.is_active || undefined,
                per_page: next.per_page || DEFAULT_CATALOG_PER_PAGE,
                page: next.page,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

export default function BusinessProductsIndex({
    products,
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

    const principalCategories = useMemo(
        () =>
            options.categories.filter(
                (category) =>
                    category.is_root !== false &&
                    !category.parent_id &&
                    (filters.branch_id
                        ? String(category.branch_id) === filters.branch_id
                        : true),
            ),
        [options.categories, filters.branch_id],
    );

    const subcategories = useMemo(
        () =>
            options.categories.filter(
                (category) =>
                    category.parent_id !== null &&
                    category.parent_id !== undefined &&
                    String(category.parent_id) === filters.category_id &&
                    (filters.branch_id
                        ? String(category.branch_id) === filters.branch_id
                        : true),
            ),
        [options.categories, filters.category_id, filters.branch_id],
    );

    return (
        <>
            <Head title="Productos" />
            <PageContainer>
                <PageHeader
                    title="Productos"
                    description="Catálogo, precios y personalización por sucursal."
                    actions={
                        <Button asChild>
                            <Link href={create.url()}>Nuevo producto</Link>
                        </Button>
                    }
                />

                <div className="mb-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Buscar producto..."
                    />
                    <FilterSelect
                        label="Sucursal"
                        value={filters.branch_id || ''}
                        onChange={(event) =>
                            visitFilters({
                                ...filters,
                                branch_id: event.target.value,
                                category_id: '',
                                subcategory_id: '',
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
                        label="Categoría"
                        value={filters.category_id || ''}
                        onChange={(event) =>
                            visitFilters({
                                ...filters,
                                category_id: event.target.value,
                                subcategory_id: '',
                                page: 1,
                            })
                        }
                    >
                        <option value="">Todas las categorías</option>
                        {principalCategories.map((category) => (
                            <option key={category.value} value={category.value}>
                                {category.label.includes(' › ')
                                    ? category.label.split(' › ')[0]
                                    : category.label}
                            </option>
                        ))}
                    </FilterSelect>
                    <FilterSelect
                        label="Subcategoría"
                        value={filters.subcategory_id || ''}
                        disabled={filters.category_id === ''}
                        onChange={(event) =>
                            visitFilters({
                                ...filters,
                                subcategory_id: event.target.value,
                                page: 1,
                            })
                        }
                    >
                        <option value="">
                            {filters.category_id === ''
                                ? 'Elige una categoría primero'
                                : subcategories.length === 0
                                  ? 'Sin subcategorías'
                                  : 'Todas las subcategorías'}
                        </option>
                        {subcategories.map((category) => (
                            <option key={category.value} value={category.value}>
                                {category.label.includes(' › ')
                                    ? category.label.split(' › ').pop()
                                    : category.label}
                            </option>
                        ))}
                    </FilterSelect>
                    <FilterSelect
                        label="Disponibilidad"
                        value={filters.is_available || ''}
                        onChange={(event) =>
                            visitFilters({
                                ...filters,
                                is_available: event.target.value,
                                page: 1,
                            })
                        }
                    >
                        <option value="">Disponibilidad</option>
                        <option value="1">Disponible</option>
                        <option value="0">Agotado</option>
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
                        <option value="">Estado</option>
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
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
                    data={products.data}
                    rowKey={(row) => row.id}
                    pagination={{
                        page: products.current_page,
                        lastPage: products.last_page,
                        onPageChange: (page) =>
                            visitFilters({ ...filters, page }),
                    }}
                />
            </PageContainer>
        </>
    );
}

BusinessProductsIndex.layout = {
    breadcrumbs: [
        { title: 'Business', href: business.home.url() },
        { title: 'Productos', href: index.url() },
    ],
};
