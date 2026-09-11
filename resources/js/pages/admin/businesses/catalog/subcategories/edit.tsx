import { Head, Link } from '@inertiajs/react';
import type {
    CatalogFormOptions,
    CategoryFormValues,
} from '@/components/catalog/category-form';
import { CategoryForm } from '@/components/catalog/category-form';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import {
    index,
    update,
} from '@/routes/admin/businesses/catalog/subcategories';

type Props = {
    business: { id: number; name: string };
    subcategory: CategoryFormValues & { parent_id?: number | string | null };
    options: CatalogFormOptions;
};

export default function AdminCatalogSubcategoriesEdit({
    business,
    subcategory,
    options,
}: Props) {
    const listUrl = index.url(business.id);

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
                        lockBranch
                        action={update([business.id, subcategory.id!])}
                        submitLabel="Guardar cambios"
                        cancelSlot={
                            <Button variant="outline" asChild>
                                <Link href={listUrl}>Cancelar</Link>
                            </Button>
                        }
                    />
                </div>
            </PageContainer>
        </>
    );
}
