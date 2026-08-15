import { Head, Link } from '@inertiajs/react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import { DataTable } from '@/components/data-display/data-table';
import type { DataTableColumn } from '@/components/data-display/data-table';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';

type PromotionRow = {
    id: number;
    name: string;
    promotion_price: string;
    status_label: string;
};

type Props = {
    business: { id: number; name: string };
    promotions: { data: PromotionRow[] };
    options: CatalogFormOptions;
};

export default function AdminCatalogPromotionsIndex({
    business,
    promotions,
}: Props) {
    const base = `/admin/businesses/${business.id}/catalog`;

    const columns: DataTableColumn<PromotionRow>[] = [
        { key: 'name', header: 'Promoción', cell: (row) => row.name },
        {
            key: 'price',
            header: 'Precio',
            cell: (row) => formatMoney(row.promotion_price),
        },
        {
            key: 'status',
            header: 'Estado',
            cell: (row) => row.status_label,
        },
        {
            key: 'actions',
            header: 'Acciones',
            className: 'text-right',
            cell: (row) => (
                <Button variant="ghost" size="sm" asChild>
                    <Link href={`${base}/promotions/${row.id}/edit`}>
                        Editar
                    </Link>
                </Button>
            ),
        },
    ];

    return (
        <>
            <Head title={`Promociones · ${business.name}`} />
            <PageContainer>
                <PageHeader
                    title="Promociones"
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={base}>Volver</Link>
                            </Button>
                            <Button asChild>
                                <Link href={`${base}/promotions/create`}>
                                    Nueva
                                </Link>
                            </Button>
                        </>
                    }
                />
                <DataTable
                    columns={columns}
                    data={promotions.data}
                    rowKey={(row) => row.id}
                />
            </PageContainer>
        </>
    );
}
