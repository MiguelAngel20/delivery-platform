import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { MockPromotion } from '@/apps/storefront/mocks';
import { formatMoney } from '@/apps/storefront/mocks';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import restaurants from '@/routes/restaurants';

type MobilePromotionCardProps = {
    promotion: MockPromotion;
    className?: string;
    variant?: 'home' | 'restaurant';
    canOrder?: boolean;
    onAdd?: () => void;
};

export function MobilePromotionCard({
    promotion,
    className,
    variant = 'home',
    canOrder = false,
    onAdd,
}: MobilePromotionCardProps) {
    const title = promotion.name;
    const priceLabel =
        promotion.price > 0
            ? promotion.has_size_options
                ? `Desde ${formatMoney(promotion.price)}`
                : formatMoney(promotion.price)
            : 'Promoción';

    const content = (
        <article
            className={cn(
                'flex gap-3 rounded-xl border border-border bg-surface p-3 shadow-sm',
                className,
            )}
        >
            <div className="relative w-16 min-h-16 shrink-0 self-stretch overflow-hidden rounded-lg bg-secondary sm:w-[4.5rem]">
                {promotion.image_url ? (
                    <img
                        src={promotion.image_url}
                        alt={promotion.name}
                        className="absolute inset-0 size-full object-cover"
                    />
                ) : (
                    <div className="absolute inset-0 flex items-center justify-center text-lg font-semibold text-navy">
                        {title.slice(0, 1)}
                    </div>
                )}
            </div>

            <div className="flex min-w-0 flex-1 flex-col justify-between gap-1.5">
                <div className="space-y-1.5">
                    <h3 className="line-clamp-2 text-base font-semibold leading-snug text-navy">
                        {title}
                    </h3>
                    {promotion.description ? (
                        <p className="line-clamp-2 break-words text-sm text-muted-foreground">
                            {promotion.description}
                        </p>
                    ) : null}
                </div>
                <div className="flex items-center justify-between gap-2">
                    {variant === 'home' && promotion.restaurant_name ? (
                        <p className="min-w-0 truncate text-xs text-muted-foreground">
                            {promotion.restaurant_name}
                        </p>
                    ) : null}
                    <span
                        className={cn(
                            'shrink-0 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary',
                            canOrder && onAdd && 'mr-auto',
                        )}
                    >
                        {priceLabel}
                    </span>
                    {canOrder && onAdd ? (
                        <Button
                            type="button"
                            size="sm"
                            className="size-8 rounded-full p-0"
                            aria-label={`Agregar ${promotion.name}`}
                            onClick={(event) => {
                                event.preventDefault();
                                event.stopPropagation();
                                onAdd();
                            }}
                        >
                            <Plus className="size-4" />
                        </Button>
                    ) : null}
                </div>
            </div>
        </article>
    );

    if (!promotion.restaurantSlug || (canOrder && onAdd)) {
        return content;
    }

    return (
        <Link href={restaurants.show.url(promotion.restaurantSlug)} className="block">
            {content}
        </Link>
    );
}
