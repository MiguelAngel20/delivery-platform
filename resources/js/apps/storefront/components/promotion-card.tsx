import { Link } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
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
                'overflow-hidden rounded-2xl border border-border bg-surface shadow-sm transition-all hover:border-primary/40 hover:shadow-md',
                square ? 'relative aspect-square' : '',
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
                        ) : (
                            <div className="flex size-full items-center justify-center bg-gradient-to-br from-primary/20 to-navy/10 text-3xl font-semibold text-navy">
                                {promotion.name.slice(0, 1)}
                            </div>
                        )}
                    </div>
                    <div className="absolute inset-0 bg-gradient-to-t from-navy/85 via-navy/25 to-transparent" />
                    <span className="absolute top-2 left-2 inline-flex items-center gap-1 rounded-full bg-primary px-2 py-0.5 text-[10px] font-semibold text-primary-foreground shadow-sm">
                        <Sparkles className="size-3" />
                        Promo
                    </span>
                    {promotion.is_affiliated ? (
                        <span className="absolute top-2 right-2 rounded-md bg-white/90 px-1.5 py-0.5 text-[10px] font-semibold text-navy">
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
                    <div className="relative h-36 overflow-hidden bg-secondary">
                        {promotion.image_url ? (
                            <img
                                src={promotion.image_url}
                                alt={promotion.name}
                                className="size-full object-cover"
                            />
                        ) : (
                            <div className="flex size-full items-center justify-center bg-gradient-to-br from-primary/25 via-accent to-secondary text-4xl font-semibold text-navy">
                                {promotion.name.slice(0, 1)}
                            </div>
                        )}
                        <div className="absolute inset-0 bg-gradient-to-t from-navy/50 to-transparent" />
                        <span className="absolute top-2 left-2 inline-flex items-center gap-1 rounded-full bg-primary px-2.5 py-1 text-[11px] font-semibold text-primary-foreground shadow-sm">
                            <Sparkles className="size-3" />
                            Promoción
                        </span>
                        {promotion.is_affiliated ? (
                            <span className="absolute top-2 right-2 rounded-md bg-white/90 px-1.5 py-0.5 text-[10px] font-semibold text-navy">
                                Afiliada
                            </span>
                        ) : null}
                    </div>
                    <div className="space-y-1.5 p-4">
                        <h3 className="font-semibold text-navy">
                            {promotion.name}
                        </h3>
                        {promotion.composition ? (
                            <p className="line-clamp-2 text-sm text-muted-foreground">
                                {promotion.composition}
                            </p>
                        ) : null}
                        <p className="pt-1 text-lg font-semibold text-primary">
                            {promotion.price > 0
                                ? formatMoney(promotion.price)
                                : 'Ver detalle'}
                        </p>
                    </div>
                </>
            )}
        </article>
    );

    if (!promotion.restaurantSlug) {
        return content;
    }

    return (
        <Link
            href={restaurants.show.url(promotion.restaurantSlug)}
            className={cn(square && 'block')}
        >
            {content}
        </Link>
    );
}
