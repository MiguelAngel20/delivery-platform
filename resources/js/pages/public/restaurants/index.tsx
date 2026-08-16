import { Head } from '@inertiajs/react';
import { CustomOrderEntry } from '@/apps/storefront/components/custom-order-entry';
import { RestaurantCard } from '@/apps/storefront/components/restaurant-card';
import type { MockRestaurant } from '@/apps/storefront/mocks';
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
            <Head title="Negocios" />
            <PageContainer className="gap-4 px-4 py-4 md:px-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-navy">
                        Negocios
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Todas las empresas registradas · primero las afiliadas
                    </p>
                </div>
                {restaurants.data.length === 0 ? (
                    <EmptyState title="No hay negocios" />
                ) : (
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                        {restaurants.data.map((restaurant) => (
                            <RestaurantCard
                                key={restaurant.id}
                                restaurant={restaurant}
                                square
                            />
                        ))}
                    </div>
                )}
                <CustomOrderEntry />
            </PageContainer>
        </>
    );
}
