import { useEffect, useRef, useState } from 'react';
import { PromotionCard } from '@/apps/storefront/components/promotion-card';
import type { MockPromotion } from '@/apps/storefront/mocks';
import { cn } from '@/lib/utils';

type RestaurantPromotionsCarouselProps = {
    promotions: MockPromotion[];
    className?: string;
};

const AUTO_ADVANCE_MS = 4000;
const SWIPE_THRESHOLD_PX = 48;

export function RestaurantPromotionsCarousel({
    promotions,
    className,
}: RestaurantPromotionsCarouselProps) {
    const [index, setIndex] = useState(0);
    const [dragOffset, setDragOffset] = useState(0);
    const [isDragging, setIsDragging] = useState(false);
    const dragStartX = useRef(0);
    const dragDelta = useRef(0);
    const pointerId = useRef<number | null>(null);
    const pauseUntil = useRef(0);

    useEffect(() => {
        setIndex(0);
        setDragOffset(0);
    }, [promotions]);

    useEffect(() => {
        if (promotions.length <= 1) {
            return;
        }

        const timer = window.setInterval(() => {
            if (Date.now() < pauseUntil.current || isDragging) {
                return;
            }

            setIndex((current) => (current + 1) % promotions.length);
        }, AUTO_ADVANCE_MS);

        return () => window.clearInterval(timer);
    }, [promotions.length, isDragging]);

    const goTo = (next: number): void => {
        if (promotions.length === 0) {
            return;
        }

        const normalized =
            ((next % promotions.length) + promotions.length) %
            promotions.length;
        setIndex(normalized);
        pauseUntil.current = Date.now() + AUTO_ADVANCE_MS;
    };

    const onPointerDown = (
        event: React.PointerEvent<HTMLDivElement>,
    ): void => {
        if (promotions.length <= 1 || event.button !== 0) {
            return;
        }

        pointerId.current = event.pointerId;
        dragStartX.current = event.clientX;
        dragDelta.current = 0;
        setIsDragging(true);
        setDragOffset(0);
        event.currentTarget.setPointerCapture(event.pointerId);
    };

    const onPointerMove = (
        event: React.PointerEvent<HTMLDivElement>,
    ): void => {
        if (!isDragging || pointerId.current !== event.pointerId) {
            return;
        }

        const delta = event.clientX - dragStartX.current;
        dragDelta.current = delta;
        setDragOffset(delta);
    };

    const endDrag = (event: React.PointerEvent<HTMLDivElement>): void => {
        if (!isDragging || pointerId.current !== event.pointerId) {
            return;
        }

        const delta = dragDelta.current;
        setIsDragging(false);
        setDragOffset(0);
        pointerId.current = null;

        try {
            event.currentTarget.releasePointerCapture(event.pointerId);
        } catch {
            // Capture may already be released.
        }

        if (Math.abs(delta) < SWIPE_THRESHOLD_PX) {
            pauseUntil.current = Date.now() + AUTO_ADVANCE_MS;
            return;
        }

        if (delta < 0) {
            goTo(index + 1);
        } else {
            goTo(index - 1);
        }
    };

    if (promotions.length === 0) {
        return null;
    }

    const trackOffset =
        promotions.length > 1
            ? `calc(-${index * 100}% + ${dragOffset}px)`
            : '0px';

    return (
        <div
            className={cn('space-y-3 md:hidden', className)}
            aria-roledescription="carrusel"
            aria-label="Promociones del negocio"
        >
            <div
                className={cn(
                    'overflow-hidden touch-pan-y',
                    promotions.length > 1 && 'cursor-grab',
                    isDragging && 'cursor-grabbing',
                )}
                onPointerDown={onPointerDown}
                onPointerMove={onPointerMove}
                onPointerUp={endDrag}
                onPointerCancel={endDrag}
            >
                <div
                    className={cn(
                        'flex',
                        !isDragging &&
                            'transition-transform duration-500 ease-out',
                    )}
                    style={{ transform: `translateX(${trackOffset})` }}
                >
                    {promotions.map((promotion) => (
                        <div
                            key={promotion.id}
                            className="w-full shrink-0 select-none px-0.5"
                        >
                            <PromotionCard promotion={promotion} />
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
                            onClick={() => goTo(promotionIndex)}
                        />
                    ))}
                </div>
            ) : null}
        </div>
    );
}
