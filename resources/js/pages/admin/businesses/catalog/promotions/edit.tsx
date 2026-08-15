import { Head, Link } from '@inertiajs/react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import {
    PromotionForm
} from '@/components/catalog/promotion-form';
import type {PromotionFormValues} from '@/components/catalog/promotion-form';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';

type Props = {
    business: { id: number; name: string };
    promotion: PromotionFormValues & {
        items: NonNullable<PromotionFormValues['items']>;
    };
    options: CatalogFormOptions;
};

export default function AdminCatalogPromotionsEdit({
    business,
    promotion,
    options,
}: Props) {
    const base = `/admin/businesses/${business.id}/catalog`;

    return (
        <>
            <Head title={`Editar ${promotion.name}`} />
            <PageContainer>
                <PageHeader title={promotion.name} />
                <div className="rounded-xl border border-border bg-white p-4 md:p-6">
                    <PromotionForm
                        options={options}
                        promotion={{
                            ...promotion,
                            branch_id: String(promotion.branch_id),
                            items: promotion.items.map((item) => ({
                                ...item,
                                product_id: item.product_id
                                    ? String(item.product_id)
                                    : '',
                                quantity: String(item.quantity ?? 1),
                            })),
                        }}
                        action={{
                            url: `${base}/promotions/${promotion.id}`,
                            method: 'post',
                        }}
                        submitLabel="Guardar"
                        cancelSlot={
                            <Button variant="outline" asChild>
                                <Link href={`${base}/promotions`}>
                                    Cancelar
                                </Link>
                            </Button>
                        }
                    />
                </div>
            </PageContainer>
        </>
    );
}
