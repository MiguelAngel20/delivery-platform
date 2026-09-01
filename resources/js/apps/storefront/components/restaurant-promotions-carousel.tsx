import { useEffect, useState } from 'react';
import { MobilePromotionCard } from '@/apps/storefront/components/mobile-promotion-card';
import { PromotionsCarousel } from '@/apps/storefront/components/promotions-carousel';
import {
    PROMOTION_AUTO_ADVANCE_MS,
    useCarouselAutoAdvance,
    useCarouselPauseHandlers,
} from '@/apps/storefront/hooks/use-carousel-auto-advance';
import type { MockPromotion } from '@/apps/storefront/mocks';
import { cn } from '@/lib/utils';

type RestaurantPromotionsCarouselProps = {
    promotions: MockPromotion[];
    className?: string;
    canOrder?: boolean;
    onAdd?: (promotionId: string) => void;
};

function RestaurantPromotionsMobileCarousel({
    promotions,
    className,
    canOrder = false,
    onAdd,
}: RestaurantPromotionsCarouselProps) {
    const [index, setIndex] = useState(0);
    const { isPaused, pauseHandlers } = useCarouselPauseHandlers();

    useEffect(() => {
        setIndex(0);
    }, [promotions]);

    useCarouselAutoAdvance(
        promotions.length > 1,
        isPaused,
        () => setIndex((current) => (current + 1) % promotions.length),
        PROMOTION_AUTO_ADVANCE_MS,
    );

    if (promotions.length === 0) {
        return null;
    }

    return (
        <div
            className={cn('space-y-3 md:hidden', className)}
            aria-roledescription="carrusel"
            aria-label="Promociones del negocio"
            {...pauseHandlers}
        >
            <div className="overflow-hidden">
                <div
                    className="flex transition-transform duration-500 ease-out"
                    style={{ transform: `translateX(-${index * 100}%)` }}
                >
                    {promotions.map((promotion) => (
                        <div
                            key={promotion.id}
                            className="w-full shrink-0 px-0.5"
                        >
                            <MobilePromotionCard
                                promotion={promotion}
                                variant="restaurant"
                                canOrder={canOrder}
                                onAdd={
                                    onAdd
                                        ? () => onAdd(promotion.id)
                                        : undefined
                                }
                            />
                        </div>
                    ))}
                </div>
            </div>

            {promotions.length > 1 ? (
                <div className="flex justify-center gap-1.5">
                    {promotions.map((promotion, promotionIndex) => (
                        <button
                            key={promotion.id}
                            type="button"
                            aria-label={`Ir a promoción ${promotionIndex + 1}`}
                            aria-current={
                                promotionIndex === index ? 'true' : undefined
                            }
                            className={cn(
                                'size-2 rounded-full transition-colors',
                                promotionIndex === index
                                    ? 'bg-navy'
                                    : 'bg-border hover:bg-muted-foreground/40',
                            )}
                            onClick={() => setIndex(promotionIndex)}
                        />
                    ))}
                </div>
            ) : null}
        </div>
    );
}

export function RestaurantPromotionsCarousel({
    promotions,
    className,
    canOrder = false,
    onAdd,
}: RestaurantPromotionsCarouselProps) {
    if (promotions.length === 0) {
        return null;
    }

    return (
        <div className={className}>
            <RestaurantPromotionsMobileCarousel
                promotions={promotions}
                canOrder={canOrder}
                onAdd={onAdd}
            />
            <PromotionsCarousel
                promotions={promotions}
                cardVariant="restaurant"
                canOrder={canOrder}
                onAdd={onAdd}
            />
        </div>
    );
}
