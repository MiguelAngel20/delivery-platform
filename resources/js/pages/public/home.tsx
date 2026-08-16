import { Head, Link, usePage } from '@inertiajs/react';
import {
    AffiliatedPartnersCarousel,
    type AffiliatedPartner,
} from '@/apps/storefront/components/affiliated-partners-carousel';
import { CustomOrderEntry } from '@/apps/storefront/components/custom-order-entry';
import { MobileCategoryTabs } from '@/apps/storefront/components/mobile-category-tabs';
import { MobilePromotionsCarousel } from '@/apps/storefront/components/mobile-promotions-carousel';
import { PromotionsCarousel } from '@/apps/storefront/components/promotions-carousel';
import { RestaurantsGrid } from '@/apps/storefront/components/restaurants-grid';
import type {
    MockCategory,
    MockPromotion,
    MockRestaurant,
} from '@/apps/storefront/mocks';
import { PageContainer } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import promotionsRoute from '@/routes/promotions';

type Props = {
    restaurants?: MockRestaurant[];
    affiliatedPartners?: AffiliatedPartner[];
    promotions?: MockPromotion[];
    filters?: {
        category?: string | null;
    };
};

export default function PublicHome({
    restaurants = [],
    affiliatedPartners = [],
    promotions = [],
    filters = {},
}: Props) {
    const page = usePage();
    const categories =
        (
            page.props as {
                storefront?: { categories?: MockCategory[] };
            }
        ).storefront?.categories ?? [];
    const selectedCategory = filters.category ?? null;

    return (
        <>
            <Head title="Inicio" />
            <PageContainer className="gap-6 px-4 py-4 md:px-6">
                <MobileCategoryTabs
                    categories={categories}
                    selectedSlug={selectedCategory}
                />

                <AffiliatedPartnersCarousel partners={affiliatedPartners} />

                <section className="space-y-3">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="text-lg font-semibold text-navy">
                            Promociones
                        </h2>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={promotionsRoute.index()}>Ver todas</Link>
                        </Button>
                    </div>
                    <MobilePromotionsCarousel promotions={promotions} />
                    <PromotionsCarousel promotions={promotions} />
                </section>

                <RestaurantsGrid restaurants={restaurants} title="Negocios" />

                <CustomOrderEntry />
            </PageContainer>
        </>
    );
}
