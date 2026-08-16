import { Link } from '@inertiajs/react';
import type { MockRestaurant } from '@/apps/storefront/mocks';
import { StatusBadge } from '@/components/data-display/status-badge';
import { cn } from '@/lib/utils';
import restaurants from '@/routes/restaurants';

type RestaurantCardProps = {
    restaurant: MockRestaurant;
    className?: string;
    square?: boolean;
};

export function RestaurantCard({
    restaurant,
    className,
    square = false,
}: RestaurantCardProps) {
    return (
        <Link
            href={restaurants.show(restaurant.slug)}
            className={cn(
                'block overflow-hidden rounded-xl border border-border bg-surface shadow-sm transition-colors hover:border-primary/40',
                className,
            )}
        >
            {square ? (
                <>
                    <div className="relative aspect-square bg-secondary">
                        {restaurant.logo_url ? (
                            <img
                                src={restaurant.logo_url}
                                alt=""
                                className="size-full object-cover"
                            />
                        ) : (
                            <div className="flex size-full items-center justify-center">
                                <span className="text-2xl font-semibold text-navy">
                                    {restaurant.name.slice(0, 1)}
                                </span>
                            </div>
                        )}
                        {restaurant.is_affiliated ? (
                            <span className="absolute top-2 left-2 rounded-md bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-primary-foreground">
                                Afiliada
                            </span>
                        ) : null}
                    </div>
                    <div className="space-y-1.5 p-3">
                        <h3 className="line-clamp-2 text-sm font-semibold leading-snug text-navy">
                            {restaurant.name}
                        </h3>
                        <p className="line-clamp-1 text-xs text-muted-foreground">
                            {restaurant.category}
                        </p>
                        <div className="flex items-center justify-between gap-2 text-xs">
                            <span className="truncate text-muted-foreground">
                                {restaurant.eta ?? '—'}
                            </span>
                            <span
                                className={cn(
                                    'shrink-0 font-medium',
                                    restaurant.open
                                        ? 'text-emerald-700'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {restaurant.open ? 'Abierto' : 'Cerrado'}
                            </span>
                        </div>
                    </div>
                </>
            ) : (
                <>
                    <div className="flex h-28 items-end bg-secondary px-4 py-3">
                        {restaurant.logo_url ? (
                            <img
                                src={restaurant.logo_url}
                                alt=""
                                className="size-12 rounded-lg object-cover"
                            />
                        ) : (
                            <span className="text-lg font-semibold text-navy">
                                {restaurant.name.slice(0, 1)}
                            </span>
                        )}
                    </div>
                    <div className="space-y-2 p-4">
                        <div className="flex items-start justify-between gap-2">
                            <div className="min-w-0">
                                <h3 className="font-semibold text-navy">
                                    {restaurant.name}
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    {restaurant.category}
                                </p>
                            </div>
                            <StatusBadge
                                tone={restaurant.open ? 'success' : 'neutral'}
                            >
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
                </>
            )}
        </Link>
    );
}
