import { useEffect, useState } from 'react';
import { MobilePromotionCard } from '@/apps/storefront/components/mobile-promotion-card';
import type { MockPromotion } from '@/apps/storefront/mocks';
import { cn } from '@/lib/utils';

type MobilePromotionsCarouselProps = {
    promotions: MockPromotion[];
    className?: string;
};

const AUTO_ADVANCE_MS = 5000;

export function MobilePromotionsCarousel({
    promotions,
    className,
}: MobilePromotionsCarouselProps) {
    const [index, setIndex] = useState(0);

    useEffect(() => {
        setIndex(0);
    }, [promotions]);

    useEffect(() => {
        if (promotions.length <= 1) {
            return;
        }

        const timer = window.setInterval(() => {
            setIndex((current) => (current + 1) % promotions.length);
        }, AUTO_ADVANCE_MS);

        return () => window.clearInterval(timer);
    }, [promotions]);

    if (promotions.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No hay promociones activas por ahora.
            </p>
        );
    }

    return (
        <div
            className={cn('space-y-3 md:hidden', className)}
            aria-roledescription="carrusel"
            aria-label="Promociones"
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
                            <MobilePromotionCard promotion={promotion} />
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
