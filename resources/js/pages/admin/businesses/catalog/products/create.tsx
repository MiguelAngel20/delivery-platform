import { Head, Link } from '@inertiajs/react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import { ProductForm } from '@/components/catalog/product-form';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';

type Props = {
    business: { id: number; name: string };
    options: CatalogFormOptions;
};

export default function AdminCatalogProductsCreate({
    business,
    options,
}: Props) {
    const base = `/admin/businesses/${business.id}/catalog`;

    return (
        <>
            <Head title={`Nuevo producto · ${business.name}`} />
            <PageContainer>
                <PageHeader title="Nuevo producto" />
                <div className="rounded-xl border border-border bg-white p-4 md:p-6">
                    <ProductForm
                        options={options}
                        action={{ url: `${base}/products`, method: 'post' }}
                        submitLabel="Crear producto"
                        showAcquisitionCost
                        cancelSlot={
                            <Button variant="outline" asChild>
                                <Link href={`${base}/products`}>Cancelar</Link>
                            </Button>
                        }
                    />
                </div>
            </PageContainer>
        </>
    );
}
