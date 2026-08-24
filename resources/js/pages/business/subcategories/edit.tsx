import { Head, Link } from '@inertiajs/react';
import { CategoryForm } from '@/components/catalog/category-form';
import type {
    CatalogFormOptions,
    CategoryFormValues,
} from '@/components/catalog/category-form';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import business from '@/routes/business';
import { index, update } from '@/routes/business/subcategories';

type Props = {
    subcategory: CategoryFormValues & { parent_id?: number | string | null };
    options: CatalogFormOptions;
};

export default function BusinessSubcategoriesEdit({
    subcategory,
    options,
}: Props) {
    return (
        <>
            <Head title={`Editar ${subcategory.name}`} />
            <PageContainer>
                <PageHeader
                    title={subcategory.name}
                    description="Actualiza la subcategoría y su categoría principal."
                />
                <div className="rounded-xl border border-border bg-surface p-4 md:p-6">
                    <CategoryForm
                        options={options}
                        variant="subcategory"
                        category={{
                            ...subcategory,
                            branch_id: String(subcategory.branch_id),
                            parent_id: subcategory.parent_id
                                ? String(subcategory.parent_id)
                                : '',
                        }}
                        action={update(subcategory.id!)}
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

BusinessSubcategoriesEdit.layout = {
    breadcrumbs: [
        { title: 'Business', href: business.home.url() },
        { title: 'Subcategorías', href: index.url() },
        { title: 'Editar', href: '#' },
    ],
};
