import { Head, Link } from '@inertiajs/react';
import {
    CategoryForm
} from '@/components/catalog/category-form';
import type {CatalogFormOptions, CategoryFormValues} from '@/components/catalog/category-form';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import business from '@/routes/business';
import { index, update } from '@/routes/business/categories';

type Props = {
    category: CategoryFormValues;
    options: CatalogFormOptions;
};

export default function BusinessCategoriesEdit({ category, options }: Props) {
    return (
        <>
            <Head title={`Editar ${category.name}`} />
            <PageContainer>
                <PageHeader
                    title={category.name}
                    description="Actualiza nombre, orden o estado."
                />
                <div className="rounded-xl border border-border bg-white p-4 md:p-6">
                    <CategoryForm
                        options={options}
                        category={{
                            ...category,
                            branch_id: String(category.branch_id),
                        }}
                        action={update(category.id!)}
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

BusinessCategoriesEdit.layout = {
    breadcrumbs: [
        { title: 'Business', href: business.home.url() },
        { title: 'Categorías', href: index.url() },
        { title: 'Editar', href: '#' },
    ],
};
