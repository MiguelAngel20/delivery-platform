import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import { CategoryForm } from '@/components/catalog/category-form';
import {
    DEFAULT_CATALOG_PER_PAGE,
    PerPageSelect,
} from '@/components/catalog/per-page-select';
import { DataTable } from '@/components/data-display/data-table';
import type { DataTableColumn } from '@/components/data-display/data-table';
import { StatusBadge } from '@/components/data-display/status-badge';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { BackButton } from '@/components/navigation/back-button';
import { Button } from '@/components/ui/button';
import {
    destroy,
    edit,
    index,
    store,
} from '@/routes/admin/businesses/catalog/categories';

type CategoryRow = {
    id: number;
    name: string;
    display_name?: string;
    branch_name?: string;
    is_active: boolean;
    can_delete?: boolean;
};

type Filters = {
    per_page: number;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
};

type Props = {
    business: { id: number; name: string };
    categories: Paginated<CategoryRow>;
    filters: Filters;
    options: CatalogFormOptions;
};

export default function AdminCatalogCategoriesIndex({
    business,
    categories,
    filters,
    options,
}: Props) {
    const base = `/admin/businesses/${business.id}/catalog`;
    const perPage = filters.per_page || DEFAULT_CATALOG_PER_PAGE;

    const visitFilters = (next: Partial<Filters> & { page?: number }) => {
        router.get(
            index.url(business.id, {
                query: {
                    per_page: next.per_page || DEFAULT_CATALOG_PER_PAGE,
                    page: next.page,
                },
            }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const columns = useMemo<DataTableColumn<CategoryRow>[]>(
        () => [
            {
                key: 'name',
                header: 'Categoría',
                cell: (row) => row.display_name ?? row.name,
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
                                href={edit.url([business.id, row.id])}
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
                                        `¿Eliminar la categoría "${row.name}"?`,
                                    )
                                ) {
                                    return;
                                }

                                router.delete(
                                    destroy.url([business.id, row.id]),
                                );
                            }}
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    </div>
                ),
            },
        ],
        [business.id],
    );

    return (
        <>
            <Head title={`Categorías · ${business.name}`} />
            <PageContainer>
                <PageHeader
                    title="Categorías"
                    actions={<BackButton href={base} />}
                />
                <div className="mb-6 rounded-xl border border-border bg-white p-4">
                    <CategoryForm
                        options={options}
                        variant="principal"
                        action={store(business.id)}
                        submitLabel="Crear categoría"
                    />
                </div>
                <div className="mb-4 max-w-xs">
                    <PerPageSelect
                        value={perPage}
                        onChange={(nextPerPage) =>
                            visitFilters({ per_page: nextPerPage, page: 1 })
                        }
                    />
                </div>
                <DataTable
                    columns={columns}
                    data={categories.data}
                    rowKey={(row) => row.id}
                    pagination={{
                        page: categories.current_page,
                        lastPage: categories.last_page,
                        onPageChange: (page) =>
                            visitFilters({ per_page: perPage, page }),
                    }}
                />
            </PageContainer>
        </>
    );
}
