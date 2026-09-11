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
} from '@/routes/admin/businesses/catalog/categories';

type Props = {
    business: { id: number; name: string };
    category: CategoryFormValues;
    options: CatalogFormOptions;
};

export default function AdminCatalogCategoriesEdit({
    business,
    category,
    options,
}: Props) {
    const listUrl = index.url(business.id);

    return (
        <>
            <Head title={`Editar ${category.name}`} />
            <PageContainer>
                <PageHeader
                    title={category.name}
                    description="Actualiza nombre, orden o estado."
                />
                <div className="rounded-xl border border-border bg-surface p-4 md:p-6">
                    <CategoryForm
                        options={options}
                        category={{
                            ...category,
                            branch_id: String(category.branch_id),
                            parent_id: category.parent_id
                                ? String(category.parent_id)
                                : '',
                        }}
                        lockBranch
                        action={update([business.id, category.id!])}
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
