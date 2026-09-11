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
} from '@/routes/admin/businesses/catalog/subcategories';

type SubcategoryRow = {
    id: number;
    name: string;
    parent_name?: string | null;
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
    subcategories: Paginated<SubcategoryRow>;
    filters: Filters;
    options: CatalogFormOptions;
};

export default function AdminCatalogSubcategoriesIndex({
    business,
    subcategories,
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
                                        `¿Eliminar la subcategoría "${row.name}"?`,
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
            <Head title={`Subcategorías · ${business.name}`} />
            <PageContainer>
                <PageHeader
                    title="Subcategorías"
                    description="Opcional. Organiza dentro de una categoría principal."
                    actions={<BackButton href={base} />}
                />
                <div className="mb-6 rounded-xl border border-border bg-card p-4">
                    <CategoryForm
                        options={options}
                        variant="subcategory"
                        action={store(business.id)}
                        submitLabel="Crear subcategoría"
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
                    data={subcategories.data}
                    rowKey={(row) => row.id}
                    pagination={{
                        page: subcategories.current_page,
                        lastPage: subcategories.last_page,
                        onPageChange: (page) =>
                            visitFilters({ per_page: perPage, page }),
                    }}
                />
            </PageContainer>
        </>
    );
}
