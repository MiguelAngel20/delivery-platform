import { Head, Link } from '@inertiajs/react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import { ProductForm } from '@/components/catalog/product-form';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import business from '@/routes/business';
import { index, store } from '@/routes/business/products';

type Props = {
    options: CatalogFormOptions;
};

export default function BusinessProductsCreate({ options }: Props) {
    return (
        <>
            <Head title="Nuevo producto" />
            <PageContainer>
                <PageHeader
                    title="Nuevo producto"
                    description="Define precio y personalización por sucursal."
                />
                <div className="rounded-xl border border-border bg-surface p-4 md:p-6">
                    <ProductForm
                        options={options}
                        action={store()}
                        submitLabel="Crear producto"
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

BusinessProductsCreate.layout = {
    breadcrumbs: [
        { title: 'Business', href: business.home.url() },
        { title: 'Productos', href: index.url() },
        { title: 'Nuevo', href: '#' },
    ],
};
