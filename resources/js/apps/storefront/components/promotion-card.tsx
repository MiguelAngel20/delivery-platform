import { Link } from '@inertiajs/react';
import type { MockPromotion } from '@/apps/storefront/mocks';
import { formatMoney } from '@/apps/storefront/mocks';
import { cn } from '@/lib/utils';
import restaurants from '@/routes/restaurants';

type PromotionCardProps = {
    promotion: MockPromotion;
    className?: string;
    square?: boolean;
};

export function PromotionCard({
    promotion,
    className,
    square = false,
}: PromotionCardProps) {
    const content = (
        <article
            className={cn(
                'overflow-hidden rounded-xl border border-border bg-surface shadow-sm',
                square ? 'relative aspect-square' : 'p-4',
                className,
            )}
        >
            {square ? (
                <>
                    <div className="absolute inset-0 bg-secondary">
                        {promotion.image_url ? (
                            <img
                                src={promotion.image_url}
                                alt={promotion.name}
                                className="size-full object-cover"
                            />
                        ) : null}
                    </div>
                    <div className="absolute inset-0 bg-gradient-to-t from-navy/80 via-navy/20 to-transparent" />
                    {promotion.is_affiliated ? (
                        <span className="absolute top-2 left-2 rounded-md bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-primary-foreground">
                            Afiliada
                        </span>
                    ) : null}
                    <div className="absolute inset-x-0 bottom-0 space-y-1 p-3 text-white">
                        <h3 className="line-clamp-2 text-sm font-semibold">
                            {promotion.name}
                        </h3>
                        <p className="line-clamp-1 text-xs text-white/80">
                            {promotion.composition}
                        </p>
                        <p className="text-sm font-semibold">
                            {promotion.price > 0
                                ? formatMoney(promotion.price)
                                : 'Ver detalle'}
                        </p>
                    </div>
                </>
            ) : (
                <>
                    <p className="text-xs font-medium uppercase tracking-wide text-primary">
                        Promoción
                    </p>
                    <h3 className="mt-1 font-semibold text-navy">
                        {promotion.name}
                    </h3>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {promotion.composition}
                    </p>
                    <p className="mt-3 text-base font-semibold text-navy">
                        {promotion.price > 0
                            ? formatMoney(promotion.price)
                            : 'Ver detalle'}
                    </p>
                </>
            )}
        </article>
    );

    if (!promotion.restaurantSlug) {
        return content;
    }

    return (
        <Link
            href={restaurants.show(promotion.restaurantSlug)}
            className={cn(square && 'block')}
        >
            {content}
        </Link>
    );
}
