import { Head } from '@inertiajs/react';
import { RestaurantCard } from '@/apps/storefront/components/restaurant-card';
import { SearchBar } from '@/apps/storefront/components/search-bar';
import type { MockRestaurant } from '@/apps/storefront/mocks';
import { CustomOrderEntry } from '@/apps/storefront/components/custom-order-entry';
import { EmptyState } from '@/components/feedback/empty-state';
import { PageContainer } from '@/components/layout/page';

type Paginated<T> = {
    data: T[];
};

type Props = {
    restaurants: Paginated<MockRestaurant>;
};

export default function RestaurantsIndex({ restaurants }: Props) {
    return (
        <>
            <Head title="Restaurantes" />
            <PageContainer className="gap-4 px-4 py-4 md:px-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-navy">
                        Restaurantes
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Descubre establecimientos cerca de ti
                    </p>
                </div>
                <SearchBar />
                {restaurants.data.length === 0 ? (
                    <EmptyState title="No hay restaurantes" />
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {restaurants.data.map((restaurant) => (
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
