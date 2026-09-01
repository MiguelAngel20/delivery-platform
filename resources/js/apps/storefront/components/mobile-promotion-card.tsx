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
    const title =
        variant === 'restaurant'
            ? promotion.name
            : (promotion.restaurant_name ?? promotion.name);
    const categoryLine =
        variant === 'restaurant'
            ? promotion.composition
            : [promotion.business_type, promotion.composition]
                  .filter(Boolean)
                  .join(' · ');

    const content = (
        <article
            className={cn(
                'flex gap-3 rounded-xl border border-border bg-surface p-3 shadow-sm',
                className,
            )}
        >
            <div className="relative size-24 shrink-0 overflow-hidden rounded-lg bg-secondary sm:size-28 md:size-32">
                {promotion.image_url ? (
                    <img
                        src={promotion.image_url}
                        alt={promotion.name}
                        className="size-full object-cover"
                    />
                ) : (
                    <div className="flex size-full items-center justify-center text-lg font-semibold text-navy">
                        {title.slice(0, 1)}
                    </div>
                )}
            </div>

            <div className="flex min-w-0 flex-1 flex-col justify-center gap-1.5">
                <h3 className="line-clamp-2 text-base font-semibold leading-snug text-navy">
                    {title}
                </h3>
                {categoryLine ? (
                    <p className="line-clamp-2 text-xs text-muted-foreground">
                        {categoryLine}
                    </p>
                ) : null}
                {variant === 'home' ? (
                    <p className="line-clamp-1 text-xs text-muted-foreground">
                        {promotion.name}
                    </p>
                ) : null}
                <div className="flex flex-wrap items-center gap-2 pt-0.5">
                    {promotion.price > 0 ? (
                        <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">
                            {formatMoney(promotion.price)}
                        </span>
                    ) : (
                        <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">
                            Promoción
                        </span>
                    )}
                    {canOrder && onAdd ? (
                        <Button
                            type="button"
                            size="sm"
                            className="ml-auto size-8 rounded-full p-0"
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
