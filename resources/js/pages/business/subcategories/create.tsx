import { Head, Link } from '@inertiajs/react';
import { CategoryForm } from '@/components/catalog/category-form';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import business from '@/routes/business';
import { index, store } from '@/routes/business/subcategories';

type Props = {
    options: CatalogFormOptions;
};

export default function BusinessSubcategoriesCreate({ options }: Props) {
    return (
        <>
            <Head title="Nueva subcategoría" />
            <PageContainer>
                <PageHeader
                    title="Nueva subcategoría"
                    description="Elige primero la categoría principal y luego el nombre de la subcategoría."
                />
                <div className="rounded-xl border border-border bg-surface p-4 md:p-6">
                    <CategoryForm
                        options={options}
                        variant="subcategory"
                        action={store()}
                        submitLabel="Crear subcategoría"
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

BusinessSubcategoriesCreate.layout = {
    breadcrumbs: [
        { title: 'Business', href: business.home.url() },
        { title: 'Subcategorías', href: index.url() },
        { title: 'Nueva', href: '#' },
    ],
};
