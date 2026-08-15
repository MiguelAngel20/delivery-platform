import { Head, Link } from '@inertiajs/react';
import {
    CategoryForm
} from '@/components/catalog/category-form';
import type {CatalogFormOptions, CategoryFormValues} from '@/components/catalog/category-form';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import business from '@/routes/business';
import { index, store } from '@/routes/business/categories';

type Props = {
    options: CatalogFormOptions;
};

export default function BusinessCategoriesCreate({ options }: Props) {
    return (
        <>
            <Head title="Nueva categoría" />
            <PageContainer>
                <PageHeader
                    title="Nueva categoría"
                    description="La categoría pertenece a una sucursal."
                />
                <div className="rounded-xl border border-border bg-white p-4 md:p-6">
                    <CategoryForm
                        options={options}
                        action={store()}
                        submitLabel="Crear categoría"
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

BusinessCategoriesCreate.layout = {
    breadcrumbs: [
        { title: 'Business', href: business.home.url() },
        { title: 'Categorías', href: index.url() },
        { title: 'Nueva', href: '#' },
    ],
};
