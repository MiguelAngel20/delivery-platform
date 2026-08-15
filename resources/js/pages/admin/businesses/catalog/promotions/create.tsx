import { Head, Link } from '@inertiajs/react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import { PromotionForm } from '@/components/catalog/promotion-form';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';

type Props = {
    business: { id: number; name: string };
    options: CatalogFormOptions;
};

export default function AdminCatalogPromotionsCreate({
    business,
    options,
}: Props) {
    const base = `/admin/businesses/${business.id}/catalog`;

    return (
        <>
            <Head title={`Nueva promoción · ${business.name}`} />
            <PageContainer>
                <PageHeader title="Nueva promoción" />
                <div className="rounded-xl border border-border bg-white p-4 md:p-6">
                    <PromotionForm
                        options={options}
                        action={{ url: `${base}/promotions`, method: 'post' }}
                        submitLabel="Crear promoción"
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
