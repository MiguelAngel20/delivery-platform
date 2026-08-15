import { Head, Link } from '@inertiajs/react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import {
    ProductForm
} from '@/components/catalog/product-form';
import type {ProductFormValues} from '@/components/catalog/product-form';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import business from '@/routes/business';
import { index, update } from '@/routes/business/products';

type Props = {
    product: ProductFormValues & {
        product_category_id?: number | string | null;
        option_groups?: ProductFormValues['option_groups'];
    };
    options: CatalogFormOptions;
};

export default function BusinessProductsEdit({ product, options }: Props) {
    return (
        <>
            <Head title={`Editar ${product.name}`} />
            <PageContainer>
                <PageHeader
                    title={product.name}
                    description="Información, precio, opciones y disponibilidad."
                />
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
                            option_groups: product.option_groups?.map((group) => ({
                                ...group,
                                options: group.options.map((option) => ({
                                    ...option,
                                    price_modifier: String(option.price_modifier),
                                })),
                            })),
                        }}
                        action={update(product.id!)}
                        submitLabel="Guardar cambios"
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

BusinessProductsEdit.layout = {
    breadcrumbs: [
        { title: 'Business', href: business.home.url() },
        { title: 'Productos', href: index.url() },
        { title: 'Editar', href: '#' },
    ],
};
