import { Head, Link } from '@inertiajs/react';
import { useMemo } from 'react';
import { CategoryCard } from '@/apps/storefront/components/category-card';
import { CustomOrderEntry } from '@/apps/storefront/components/custom-order-entry';
import { RestaurantCard } from '@/apps/storefront/components/restaurant-card';
import {
    searchStorefrontCategories,
    searchStorefrontRestaurants,
    type SearchProduct,
    type SearchPromotion,
} from '@/apps/storefront/lib/search-restaurants';
import type { MockCategory, MockRestaurant } from '@/apps/storefront/mocks';
import { EmptyState } from '@/components/feedback/empty-state';
import { PageContainer } from '@/components/layout/page';
import { home } from '@/routes';

type Props = {
    q?: string;
    restaurants?: MockRestaurant[];
    products?: SearchProduct[];
    promotions?: SearchPromotion[];
    categories?: MockCategory[];
};

export default function SearchIndex({
    q = '',
    restaurants: restaurantCatalog = [],
    products = [],
    promotions = [],
    categories: categoryCatalog = [],
}: Props) {
    const query = q.trim();

    const catalog = useMemo(
        () => ({
            restaurants: restaurantCatalog,
            products,
            promotions,
            categories: categoryCatalog,
        }),
        [restaurantCatalog, products, promotions, categoryCatalog],
    );

    const restaurants = useMemo(
        () => searchStorefrontRestaurants(query, catalog),
        [query, catalog],
    );
    const categories = useMemo(
        () => searchStorefrontCategories(query, categoryCatalog),
        [query, categoryCatalog],
    );

    if (query === '') {
        return (
            <>
                <Head title="Buscar" />
                <PageContainer className="gap-4 px-4 py-4 md:px-6">
                    <EmptyState
                        title="Escribe para buscar"
                        description="Busca restaurantes, comida rápida, postres y más."
                    />
                    <div className="text-center">
                        <Link
                            href={home()}
                            className="text-sm font-medium text-primary underline-offset-4 hover:underline"
                        >
                            Volver al inicio
                        </Link>
                    </div>
                </PageContainer>
            </>
        );
    }

    const hasResults = restaurants.length > 0 || categories.length > 0;

    return (
        <>
            <Head title={`Buscar: ${query}`} />
            <PageContainer className="gap-6 px-4 py-4 md:px-6">
                {!hasResults ? (
                    <EmptyState
                        title="Sin resultados"
                        description="Prueba con el nombre de un restaurante, categoría o platillo"
                    />
                ) : null}

                {categories.length > 0 ? (
                    <section className="space-y-3">
                        <h2 className="text-lg font-semibold text-navy">
                            Categorías
                        </h2>
                        <div className="flex gap-3 overflow-x-auto pb-1">
                            {categories.map((category) => (
                                <CategoryCard
                                    key={category.id}
                                    category={category}
                                />
                            ))}
                        </div>
                    </section>
                ) : null}

                {restaurants.length > 0 ? (
                    <section className="space-y-3">
                        <h2 className="text-lg font-semibold text-navy">
                            Negocios
                        </h2>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {restaurants.map((restaurant) => (
                                <RestaurantCard
                                    key={restaurant.id}
                                    restaurant={restaurant}
                                />
                            ))}
                        </div>
                    </section>
                ) : null}

                <CustomOrderEntry />
            </PageContainer>
        </>
    );
}
