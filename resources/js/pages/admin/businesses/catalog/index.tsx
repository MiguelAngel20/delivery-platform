import { Head, Link } from '@inertiajs/react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { Button } from '@/components/ui/button';

type Props = {
    business: { id: number; name: string; slug: string };
    options: CatalogFormOptions;
};

export default function AdminCatalogIndex({ business }: Props) {
    const base = `/admin/businesses/${business.id}/catalog`;

    return (
        <>
            <Head title={`Catálogo · ${business.name}`} />
            <PageContainer>
                <PageHeader
                    title={`Catálogo · ${business.name}`}
                    description="Administración PLATFORM_OPERATED"
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={`/admin/businesses/${business.id}`}>
                                Volver
                            </Link>
                        </Button>
                    }
                />
                <div className="grid gap-4 md:grid-cols-3">
                    <ContentCard title="Categorías">
                        <Button asChild>
                            <Link href={`${base}/categories`}>Abrir</Link>
                        </Button>
                    </ContentCard>
                    <ContentCard title="Productos">
                        <Button asChild>
                            <Link href={`${base}/products`}>Abrir</Link>
                        </Button>
                    </ContentCard>
                    <ContentCard title="Promociones">
                        <Button asChild>
                            <Link href={`${base}/promotions`}>Abrir</Link>
                        </Button>
                    </ContentCard>
                </div>
            </PageContainer>
        </>
    );
}
