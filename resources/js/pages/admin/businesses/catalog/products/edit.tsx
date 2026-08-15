import { Head, Link } from '@inertiajs/react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import {
    ProductForm
} from '@/components/catalog/product-form';
import type {ProductFormValues} from '@/components/catalog/product-form';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';

type Props = {
    business: { id: number; name: string };
    product: ProductFormValues;
    options: CatalogFormOptions;
};

export default function AdminCatalogProductsEdit({
    business,
    product,
    options,
}: Props) {
    const base = `/admin/businesses/${business.id}/catalog`;

    return (
        <>
            <Head title={`Editar ${product.name}`} />
            <PageContainer>
                <PageHeader title={product.name} />
                <div className="rounded-xl border border-border bg-white p-4 md:p-6">
                    <ProductForm
                        options={options}
                        product={{
                            ...product,
                            branch_id: String(product.branch_id),
                            product_category_id: product.product_category_id
                                ? String(product.product_category_id)
                                : '',
                            list_price: product.list_price ?? '',
                            acquisition_cost: product.acquisition_cost ?? '',
                        }}
                        action={{
                            url: `${base}/products/${product.id}`,
                            method: 'post',
                        }}
                        submitLabel="Guardar"
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
