import { Head, Link } from '@inertiajs/react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import { DataTable } from '@/components/data-display/data-table';
import type { DataTableColumn } from '@/components/data-display/data-table';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';

type ProductRow = {
    id: number;
    name: string;
    category_name: string;
    list_price: string | null;
};

type Props = {
    business: { id: number; name: string };
    products: { data: ProductRow[] };
    options: CatalogFormOptions;
};

export default function AdminCatalogProductsIndex({
    business,
    products,
}: Props) {
    const base = `/admin/businesses/${business.id}/catalog`;

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

    return (
        <>
            <Head title={`Productos · ${business.name}`} />
            <PageContainer>
                <PageHeader
                    title="Productos"
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={base}>Volver</Link>
                            </Button>
                            <Button asChild>
                                <Link href={`${base}/products/create`}>
                                    Nuevo
                                </Link>
                            </Button>
                        </>
                    }
                />
                <DataTable
                    columns={columns}
                    data={products.data}
                    rowKey={(row) => row.id}
                />
            </PageContainer>
        </>
    );
}
