import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { RestaurantCard } from '@/apps/storefront/components/restaurant-card';
import type { MockRestaurant } from '@/apps/storefront/mocks';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import restaurantsRoute from '@/routes/restaurants';

type RestaurantsGridProps = {
    restaurants: MockRestaurant[];
    title: string;
    className?: string;
};

const INITIAL_DESKTOP = 25;
const LOAD_MORE_STEP = 5;

export function RestaurantsGrid({
    restaurants,
    title,
    className,
}: RestaurantsGridProps) {
    const [visibleCount, setVisibleCount] = useState(INITIAL_DESKTOP);

    useEffect(() => {
        setVisibleCount(INITIAL_DESKTOP);
    }, [restaurants]);

    const visibleRestaurants = restaurants.slice(0, visibleCount);
    const hasMore = restaurants.length > visibleCount;

    return (
        <section className={cn('space-y-3', className)}>
            <div className="flex items-center justify-between gap-3">
                <h2 className="text-lg font-semibold text-navy">{title}</h2>
                <Button asChild variant="ghost" size="sm">
                    <Link href={restaurantsRoute.index()}>Ver todos</Link>
                </Button>
            </div>

            {restaurants.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No hay negocios en esta categoría por ahora.
                </p>
            ) : (
                <>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                        {visibleRestaurants.map((restaurant) => (
                            <RestaurantCard
                                key={restaurant.id}
                                restaurant={restaurant}
                                square
                            />
                        ))}
                    </div>

                    {hasMore ? (
                        <div className="flex justify-center pt-1">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    setVisibleCount(
                                        (current) => current + LOAD_MORE_STEP,
                                    )
                                }
                            >
                                Ver más
                            </Button>
                        </div>
                    ) : null}
                </>
            )}
        </section>
    );
}
