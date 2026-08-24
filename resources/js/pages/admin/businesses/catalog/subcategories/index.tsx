import { Head } from '@inertiajs/react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import { CategoryForm } from '@/components/catalog/category-form';
import { DataTable } from '@/components/data-display/data-table';
import type { DataTableColumn } from '@/components/data-display/data-table';
import { StatusBadge } from '@/components/data-display/status-badge';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { BackButton } from '@/components/navigation/back-button';

type SubcategoryRow = {
    id: number;
    name: string;
    parent_name?: string | null;
    branch_name?: string;
    is_active: boolean;
};

type Props = {
    business: { id: number; name: string };
    subcategories: { data: SubcategoryRow[] };
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

export default function AdminCatalogSubcategoriesIndex({
    business,
    subcategories,
    options,
}: Props) {
    const base = `/admin/businesses/${business.id}/catalog`;

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
                        action={{ url: `${base}/subcategories`, method: 'post' }}
                        submitLabel="Crear subcategoría"
                    />
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
