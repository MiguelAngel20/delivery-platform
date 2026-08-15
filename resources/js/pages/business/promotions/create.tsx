import { Head, Link } from '@inertiajs/react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import { PromotionForm } from '@/components/catalog/promotion-form';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import business from '@/routes/business';
import { index, store } from '@/routes/business/promotions';

type Props = {
    options: CatalogFormOptions;
};

export default function BusinessPromotionsCreate({ options }: Props) {
    return (
        <>
            <Head title="Nueva promoción" />
            <PageContainer>
                <PageHeader title="Nueva promoción" />
                <div className="rounded-xl border border-border bg-white p-4 md:p-6">
                    <PromotionForm
                        options={options}
                        action={store()}
                        submitLabel="Crear promoción"
                        cancelSlot={
                            <Button variant="outline" asChild>
                                <Link href={index.url()}>Cancelar</Link>
                            </Button>
                        }
                    />
                </div>
            </PageContainer>
        </>
    );
}

BusinessPromotionsCreate.layout = {
    breadcrumbs: [
        { title: 'Business', href: business.home.url() },
        { title: 'Promociones', href: index.url() },
        { title: 'Nueva', href: '#' },
    ],
};
