import { Link } from '@inertiajs/react';
import type { MockRestaurant } from '@/apps/storefront/mocks';
import { StatusBadge } from '@/components/data-display/status-badge';
import { cn } from '@/lib/utils';
import restaurants from '@/routes/restaurants';

type RestaurantCardProps = {
    restaurant: MockRestaurant;
    className?: string;
};

export function RestaurantCard({ restaurant, className }: RestaurantCardProps) {
    return (
        <Link
            href={restaurants.show(restaurant.slug)}
            className={cn(
                'overflow-hidden rounded-xl border border-border bg-surface shadow-sm',
                className,
            )}
        >
            <div className="flex h-28 items-end bg-secondary px-4 py-3">
                <span className="text-lg font-semibold text-navy">
                    {restaurant.name.slice(0, 1)}
                </span>
            </div>
            <div className="space-y-2 p-4">
                <div className="flex items-start justify-between gap-2">
                    <div>
                        <h3 className="font-semibold text-navy">
                            {restaurant.name}
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            {restaurant.category}
                        </p>
                    </div>
                    <StatusBadge tone={restaurant.open ? 'success' : 'neutral'}>
                        {restaurant.open ? 'Abierto' : 'Cerrado'}
                    </StatusBadge>
                </div>
                <div className="flex items-center justify-between gap-2 text-sm">
                    <span className="text-muted-foreground">
                        {restaurant.eta}
                    </span>
                    <span className="font-medium text-navy">
                        {restaurant.modeLabel}
                    </span>
                </div>
            </div>
        </Link>
    );
}
