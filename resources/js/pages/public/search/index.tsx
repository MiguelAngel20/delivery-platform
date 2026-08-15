import { Head } from '@inertiajs/react';
import { useMemo } from 'react';
import { RestaurantCard } from '@/apps/storefront/components/restaurant-card';
import { SearchBar } from '@/apps/storefront/components/search-bar';
import { mockProducts, mockRestaurants } from '@/apps/storefront/mocks';
import { CustomOrderEntry } from '@/apps/storefront/components/custom-order-entry';
import { EmptyState } from '@/components/feedback/empty-state';
import { PageContainer } from '@/components/layout/page';

type Props = {
    q?: string;
};

export default function SearchIndex({ q = '' }: Props) {
    const query = q.trim().toLowerCase();

    const restaurants = useMemo(() => {
        if (!query) {
            return mockRestaurants;
        }

        const productMatches = new Set(
            mockProducts
                .filter(
                    (product) =>
                        product.name.toLowerCase().includes(query) ||
                        product.description.toLowerCase().includes(query),
                )
                .map((product) => product.restaurantSlug),
        );

        return mockRestaurants.filter(
            (restaurant) =>
                restaurant.name.toLowerCase().includes(query) ||
                restaurant.category.toLowerCase().includes(query) ||
                productMatches.has(restaurant.slug),
        );
    }, [query]);

    return (
        <>
            <Head title="Buscar" />
            <PageContainer className="gap-4 px-4 py-4 md:px-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-navy">Buscar</h1>
                </div>
                <SearchBar defaultValue={q} autoFocus />
                {restaurants.length === 0 ? (
                    <EmptyState
                        title="Sin resultados"
                        description="Prueba con otro término"
                    />
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {restaurants.map((restaurant) => (
                            <RestaurantCard
                                key={restaurant.id}
                                restaurant={restaurant}
                            />
                        ))}
                    </div>
                )}
                <CustomOrderEntry />
            </PageContainer>
        </>
    );
}
