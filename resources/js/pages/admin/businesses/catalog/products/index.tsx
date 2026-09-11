import { Head, Link, router } from '@inertiajs/react';
import { useMemo } from 'react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import {
    DEFAULT_CATALOG_PER_PAGE,
    PerPageSelect,
} from '@/components/catalog/per-page-select';
import { DataTable } from '@/components/data-display/data-table';
import type { DataTableColumn } from '@/components/data-display/data-table';
import { FilterSelect } from '@/components/forms/filter-select';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { BackButton } from '@/components/navigation/back-button';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';

type ProductRow = {
    id: number;
    name: string;
    category_name: string;
    list_price: string | null;
};

type Filters = {
    category_id: string;
    subcategory_id: string;
    per_page: number;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
};

type Props = {
    business: { id: number; name: string };
    products: Paginated<ProductRow>;
    filters: Filters;
    options: CatalogFormOptions;
};

export default function AdminCatalogProductsIndex({
    business,
    products,
    filters,
    options,
}: Props) {
    const base = `/admin/businesses/${business.id}/catalog`;
    const perPage = filters.per_page || DEFAULT_CATALOG_PER_PAGE;

    const columns: DataTableColumn<ProductRow>[] = [
        { key: 'name', header: 'Producto', cell: (row) => row.name },
        {
            key: 'category',
            header: 'Categoría',
            cell: (row) => row.category_name,
        },
        {
            key: 'price',
            header: 'Precio',
            cell: (row) =>
                row.list_price ? formatMoney(row.list_price) : '—',
        },
        {
            key: 'actions',
            header: 'Acciones',
            className: 'text-right',
            cell: (row) => (
                <Button variant="ghost" size="sm" asChild>
                    <Link href={`${base}/products/${row.id}/edit`}>Editar</Link>
                </Button>
            ),
        },
    ];

    const visitFilters = (next: Partial<Filters> & { page?: number }) => {
        router.get(
            `${base}/products`,
            {
                category_id: next.category_id || undefined,
                subcategory_id: next.subcategory_id || undefined,
                per_page: next.per_page || DEFAULT_CATALOG_PER_PAGE,
                page: next.page,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const principalCategories = useMemo(
        () =>
            options.categories.filter(
                (category) =>
                    category.is_root !== false && !category.parent_id,
            ),
        [options.categories],
    );

    const subcategories = useMemo(
        () =>
            options.categories.filter(
                (category) =>
                    category.parent_id !== null &&
                    category.parent_id !== undefined &&
                    String(category.parent_id) === filters.category_id,
            ),
        [options.categories, filters.category_id],
    );

    return (
        <>
            <Head title={`Productos · ${business.name}`} />
            <PageContainer>
                <PageHeader
                    title="Productos"
                    actions={
                        <>
                            <BackButton href={base} />
                            <Button asChild>
                                <Link href={`${base}/products/create`}>
                                    Agregar producto
                                </Link>
                            </Button>
                        </>
                    }
                />
                <div className="mb-4 grid gap-3 md:grid-cols-3">
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
