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
    product_category_id: string;
    is_available: string;
    is_active: string;
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
    router.get(index.url({ query: next }), {}, { preserveState: true, replace: true });
}

export default function BusinessProductsIndex({
    products,
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

    const categoryOptions = options.categories.filter((category) =>
        filters.branch_id
            ? String(category.branch_id) === filters.branch_id
            : true,
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

                <div className="mb-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
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
                                product_category_id: '',
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
                        value={filters.product_category_id || ''}
                        onChange={(event) =>
                            visitFilters({
                                ...filters,
                                product_category_id: event.target.value,
                                page: 1,
                            })
                        }
                    >
                        <option value="">Todas las categorías</option>
                        {categoryOptions.map((category) => (
                            <option key={category.value} value={category.value}>
                                {category.label}
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
                </div>

                <DataTable
                    columns={columns}
                    data={products.data}
                    rowKey={(row) => row.id}
                />

                {products.last_page > 1 ? (
                    <div className="mt-4 flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={products.current_page <= 1}
                            onClick={() =>
                                visitFilters({
                                    ...filters,
                                    page: products.current_page - 1,
                                })
                            }
                        >
                            Anterior
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={products.current_page >= products.last_page}
                            onClick={() =>
                                visitFilters({
                                    ...filters,
                                    page: products.current_page + 1,
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

BusinessProductsIndex.layout = {
    breadcrumbs: [
        { title: 'Business', href: business.home.url() },
        { title: 'Productos', href: index.url() },
    ],
};
