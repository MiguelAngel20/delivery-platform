import { Head } from '@inertiajs/react';
import { CategoryCard } from '@/apps/storefront/components/category-card';
import { mockCategories } from '@/apps/storefront/mocks';
import { PageContainer } from '@/components/layout/page';

export default function CategoriesIndex() {
    return (
        <>
            <Head title="Categorías" />
            <PageContainer className="gap-4 px-4 py-4 md:px-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-navy">
                        Categorías
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Explora por tipo de comida
                    </p>
                </div>
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    {mockCategories.map((category) => (
                        <CategoryCard key={category.id} category={category} />
                    ))}
                </div>
            </PageContainer>
        </>
    );
}
