import { Head } from '@inertiajs/react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import { CategoryForm } from '@/components/catalog/category-form';
import { DataTable } from '@/components/data-display/data-table';
import type { DataTableColumn } from '@/components/data-display/data-table';
import { StatusBadge } from '@/components/data-display/status-badge';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { BackButton } from '@/components/navigation/back-button';

type CategoryRow = {
    id: number;
    name: string;
    branch_name?: string;
    is_active: boolean;
};

type Props = {
    business: { id: number; name: string };
    categories: { data: CategoryRow[] };
    options: CatalogFormOptions;
};

const columns: DataTableColumn<CategoryRow>[] = [
    { key: 'name', header: 'Categoría', cell: (row) => row.name },
    { key: 'branch', header: 'Sucursal', cell: (row) => row.branch_name ?? '—' },
    {
        key: 'status',
        header: 'Estado',
        cell: (row) => (
            <StatusBadge tone={row.is_active ? 'success' : 'neutral'}>
                {row.is_active ? 'Activa' : 'Inactiva'}
            </StatusBadge>
        ),
    },
];

export default function AdminCatalogCategoriesIndex({
    business,
    categories,
    options,
}: Props) {
    const base = `/admin/businesses/${business.id}/catalog`;

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
                        action={{ url: `${base}/categories`, method: 'post' }}
                        submitLabel="Crear categoría"
                    />
                </div>
                <DataTable
                    columns={columns}
                    data={categories.data}
                    rowKey={(row) => row.id}
                />
            </PageContainer>
        </>
    );
}
