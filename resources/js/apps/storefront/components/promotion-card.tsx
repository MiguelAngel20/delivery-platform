import { Link } from '@inertiajs/react';
import type { MockPromotion } from '@/apps/storefront/mocks';
import { formatMoney } from '@/apps/storefront/mocks';
import { cn } from '@/lib/utils';
import restaurants from '@/routes/restaurants';

type PromotionCardProps = {
    promotion: MockPromotion;
    className?: string;
};

export function PromotionCard({ promotion, className }: PromotionCardProps) {
    const content = (
        <article
            className={cn(
                'rounded-xl border border-border bg-surface p-4 shadow-sm',
                className,
            )}
        >
            <p className="text-xs font-medium uppercase tracking-wide text-primary">
                Promoción
            </p>
            <h3 className="mt-1 font-semibold text-navy">{promotion.name}</h3>
            <p className="mt-1 text-sm text-muted-foreground">
                {promotion.composition}
            </p>
            <p className="mt-3 text-base font-semibold text-navy">
                {promotion.price > 0 ? formatMoney(promotion.price) : 'Ver detalle'}
            </p>
        </article>
    );

    if (!promotion.restaurantSlug) {
        return content;
    }

    return (
        <Link href={restaurants.show(promotion.restaurantSlug)}>{content}</Link>
    );
}
