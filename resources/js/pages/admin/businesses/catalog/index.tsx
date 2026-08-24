import { Head, Link } from '@inertiajs/react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { BackButton } from '@/components/navigation/back-button';
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
                    description="Administra el menú de este negocio"
                    actions={
                        <BackButton
                            href={`/admin/businesses/${business.id}`}
                        />
                    }
                />
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <ContentCard title="Categorías">
                        <p className="mb-3 text-sm text-muted-foreground">
                            Categorías principales del menú.
                        </p>
                        <Button asChild>
                            <Link href={`${base}/categories`}>Abrir</Link>
                        </Button>
                    </ContentCard>
                    <ContentCard title="Subcategorías">
                        <p className="mb-3 text-sm text-muted-foreground">
                            Opcional. Detalles dentro de una categoría.
                        </p>
                        <Button asChild>
                            <Link href={`${base}/subcategories`}>Abrir</Link>
                        </Button>
                    </ContentCard>
                    <ContentCard title="Productos">
                        <p className="mb-3 text-sm text-muted-foreground">
                            Platillos y precios del catálogo.
                        </p>
                        <Button asChild>
                            <Link href={`${base}/products`}>Abrir</Link>
                        </Button>
                    </ContentCard>
                    <ContentCard title="Promociones">
                        <p className="mb-3 text-sm text-muted-foreground">
                            Combos y ofertas activas.
                        </p>
                        <Button asChild>
                            <Link href={`${base}/promotions`}>Abrir</Link>
                        </Button>
                    </ContentCard>
                </div>
            </PageContainer>
        </>
    );
}
