import { Head, Link } from '@inertiajs/react';
import { CategoryCard } from '@/apps/storefront/components/category-card';
import { PromotionCard } from '@/apps/storefront/components/promotion-card';
import { RestaurantCard } from '@/apps/storefront/components/restaurant-card';
import { SearchBar } from '@/apps/storefront/components/search-bar';
import { mockCategories } from '@/apps/storefront/mocks';
import type { MockPromotion, MockRestaurant } from '@/apps/storefront/mocks';
import { CustomOrderEntry } from '@/apps/storefront/components/custom-order-entry';
import { PageContainer } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import promotionsRoute from '@/routes/promotions';
import restaurantsRoute from '@/routes/restaurants';

type Props = {
    restaurants?: MockRestaurant[];
    promotions?: MockPromotion[];
};

export default function PublicHome({
    restaurants = [],
    promotions = [],
}: Props) {
    return (
        <>
            <Head title="Inicio" />
            <PageContainer className="gap-6 px-4 py-4 md:px-6">
                <SearchBar />

                <section className="space-y-3">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="text-lg font-semibold text-navy">
                            Categorías
                        </h2>
                    </div>
                    <div className="flex gap-3 overflow-x-auto pb-1">
                        {mockCategories.map((category) => (
                            <CategoryCard
                                key={category.id}
                                category={category}
                            />
                        ))}
                    </div>
                </section>

                <section className="space-y-3">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="text-lg font-semibold text-navy">
                            Promociones
                        </h2>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={promotionsRoute.index()}>Ver todas</Link>
                        </Button>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {promotions.slice(0, 3).map((promotion) => (
                            <PromotionCard
                                key={promotion.id}
                                promotion={promotion}
                            />
                        ))}
                    </div>
                </section>

                <section className="space-y-3">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="text-lg font-semibold text-navy">
                            Restaurantes
                        </h2>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={restaurantsRoute.index()}>Ver todos</Link>
                        </Button>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {restaurants.map((restaurant) => (
                            <RestaurantCard
                                key={restaurant.id}
                                restaurant={restaurant}
                            />
                        ))}
                    </div>
                </section>

                <CustomOrderEntry />
            </PageContainer>
        </>
    );
}
