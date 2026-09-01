import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { MockPromotion } from '@/apps/storefront/mocks';
import { formatMoney } from '@/apps/storefront/mocks';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import restaurants from '@/routes/restaurants';

type PromotionCardProps = {
    promotion: MockPromotion;
    className?: string;
    square?: boolean;
    squareImage?: boolean;
    canOrder?: boolean;
    onAdd?: () => void;
};

export function PromotionCard({
    promotion,
    className,
    square = false,
    squareImage = false,
    canOrder = false,
    onAdd,
}: PromotionCardProps) {
    const content = (
        <article
            className={cn(
                'overflow-hidden rounded-2xl border border-border bg-surface shadow-sm transition-all hover:border-primary/40 hover:shadow-md',
                square && !squareImage ? 'relative aspect-square' : '',
                className,
            )}
        >
            {squareImage ? (
                <>
                    <div className="relative aspect-square overflow-hidden bg-secondary">
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
                    <div className="space-y-1.5 p-4">
                        <h3 className="line-clamp-2 font-semibold text-navy">
                            {promotion.name}
                        </h3>
                        {promotion.composition ? (
                            <p className="line-clamp-2 text-sm text-muted-foreground">
                                {promotion.composition}
                            </p>
                        ) : null}
                        <p className="pt-1 text-base font-semibold text-primary">
                            {promotion.price > 0
                                ? formatMoney(promotion.price)
                                : 'Ver detalle'}
                        </p>
                        {canOrder && onAdd ? (
                            <Button
                                type="button"
                                size="sm"
                                className="mt-2 w-full"
                                onClick={(event) => {
                                    event.preventDefault();
                                    event.stopPropagation();
                                    onAdd();
                                }}
                            >
                                <Plus className="size-4" />
                                Agregar
                            </Button>
                        ) : null}
                    </div>
                </>
            ) : square ? (
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
                    <div className="pointer-events-none absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-navy/70 via-navy/20 to-transparent" />
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

    if (!promotion.restaurantSlug || (canOrder && onAdd)) {
        return content;
    }

    return (
        <Link
            href={restaurants.show.url(promotion.restaurantSlug)}
            className={cn((square || squareImage) && 'block')}
        >
            {content}
        </Link>
    );
}
