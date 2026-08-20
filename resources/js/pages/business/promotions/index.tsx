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
import {
    create,
    edit,
    index,
} from '@/routes/business/promotions';

type PromotionRow = {
    id: number;
    branch_name?: string;
    name: string;
    promotion_price: string;
    status: string;
    status_label: string;
    items: Array<{ name: string }>;
};

type Filters = {
    search: string;
    branch_id: string;
    status: string;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
};

type Props = {
    promotions: Paginated<PromotionRow>;
    filters: Filters;
    options: CatalogFormOptions;
};

function statusTone(status: string) {
    return status === 'active'
        ? 'success'
        : status === 'paused'
          ? 'warning'
          : 'neutral';
}

const columns: DataTableColumn<PromotionRow>[] = [
    {
        key: 'name',
        header: 'Promoción',
        cell: (row) => (
            <div>
                <p className="font-medium text-foreground">{row.name}</p>
                <p className="text-xs text-muted-foreground">
                    {row.items.map((item) => item.name).join(' + ')}
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
        key: 'price',
        header: 'Precio',
        cell: (row) => formatMoney(row.promotion_price),
    },
    {
        key: 'status',
        header: 'Estado',
        cell: (row) => (
            <StatusBadge tone={statusTone(row.status)}>
                {row.status_label}
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

export default function BusinessPromotionsIndex({
    promotions,
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
            <Head title="Promociones" />
            <PageContainer>
                <PageHeader
                    title="Promociones"
                    description="Combos del menú e ítems externos por sucursal."
                    actions={
                        <Button asChild>
                            <Link href={create.url()}>Nueva promoción</Link>
                        </Button>
                    }
                />

                <div className="mb-4 grid gap-3 md:grid-cols-3">
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Buscar promoción..."
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
                        value={filters.status || ''}
                        onChange={(event) =>
                            visitFilters({
                                ...filters,
                                status: event.target.value,
                                page: 1,
                            })
                        }
                    >
                        <option value="">Todos los estados</option>
                        {options.promotion_statuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </FilterSelect>
                </div>

                <DataTable
                    columns={columns}
                    data={promotions.data}
                    rowKey={(row) => row.id}
                />
            </PageContainer>
        </>
    );
}

BusinessPromotionsIndex.layout = {
    breadcrumbs: [
        { title: 'Business', href: business.home.url() },
        { title: 'Promociones', href: index.url() },
    ],
};
