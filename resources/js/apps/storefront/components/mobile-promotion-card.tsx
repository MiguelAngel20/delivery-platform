import { Link } from '@inertiajs/react';
import type { MockPromotion } from '@/apps/storefront/mocks';
import { formatMoney } from '@/apps/storefront/mocks';
import { cn } from '@/lib/utils';
import restaurants from '@/routes/restaurants';

type MobilePromotionCardProps = {
    promotion: MockPromotion;
    className?: string;
};

export function MobilePromotionCard({
    promotion,
    className,
}: MobilePromotionCardProps) {
    const title = promotion.restaurant_name ?? promotion.name;
    const categoryLine = [promotion.business_type, promotion.composition]
        .filter(Boolean)
        .join(' · ');

    const content = (
        <article
            className={cn(
                'flex gap-3 rounded-xl border border-border bg-surface p-3 shadow-sm',
                className,
            )}
        >
            <div className="relative size-24 shrink-0 overflow-hidden rounded-lg bg-secondary sm:size-28">
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
                {promotion.is_affiliated ? (
                    <span className="absolute top-1.5 left-1.5 rounded bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-primary-foreground">
                        Afiliada
                    </span>
                ) : null}
            </div>

            <div className="flex min-w-0 flex-1 flex-col justify-center gap-1.5">
                <h3 className="line-clamp-2 text-base font-semibold leading-snug text-navy">
                    {title}
                </h3>
                {categoryLine ? (
                    <p className="line-clamp-1 text-xs text-muted-foreground">
                        {categoryLine}
                    </p>
                ) : null}
                <p className="line-clamp-1 text-xs text-muted-foreground">
                    {promotion.name}
                </p>
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
                </div>
            </div>
        </article>
    );

    if (!promotion.restaurantSlug) {
        return content;
    }

    return (
        <Link href={restaurants.show(promotion.restaurantSlug)} className="block">
            {content}
        </Link>
    );
}
