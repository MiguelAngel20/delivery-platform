import { Head, Link, router } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
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
};

type Filters = {
    search: string;
    branch_id: string;
    parent_id: string;
    is_active: string;
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

const columns: DataTableColumn<SubcategoryRow>[] = [
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

export default function BusinessSubcategoriesIndex({
    subcategories,
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

                <div className="mb-4 grid gap-3 md:grid-cols-3">
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
                </div>

                <DataTable
                    columns={columns}
                    data={subcategories.data}
                    rowKey={(row) => row.id}
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
