import { ChevronLeft, ChevronRight } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
    type TransitionEvent,
} from 'react';
import { MobilePromotionCard } from '@/apps/storefront/components/mobile-promotion-card';
import {
    PROMOTION_AUTO_ADVANCE_MS,
    useCarouselAutoAdvance,
    useCarouselPauseHandlers,
} from '@/apps/storefront/hooks/use-carousel-auto-advance';
import type { MockPromotion } from '@/apps/storefront/mocks';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type PromotionsCarouselProps = {
    promotions: MockPromotion[];
    className?: string;
    cardVariant?: 'home' | 'restaurant';
    canOrder?: boolean;
    onAdd?: (promotionId: string) => void;
};

type CarouselSlide = MockPromotion & {
    carouselKey: string;
};

export function PromotionsCarousel({
    promotions,
    className,
    cardVariant = 'home',
    canOrder = false,
    onAdd,
}: PromotionsCarouselProps) {
    const trackRef = useRef<HTMLDivElement>(null);
    const isAnimatingRef = useRef(false);
    const [slideIndex, setSlideIndex] = useState(0);
    const [slideStepPx, setSlideStepPx] = useState(0);
    const [visibleCount, setVisibleCount] = useState(1);
    const [animate, setAnimate] = useState(true);
    const { isPaused, pauseHandlers } = useCarouselPauseHandlers();

    const canRotate = promotions.length > visibleCount;

    const slides = useMemo((): CarouselSlide[] => {
        const base = promotions.map((promotion) => ({
            ...promotion,
            carouselKey: promotion.id,
        }));

        if (!canRotate) {
            return base;
        }

        const trailingClones = promotions
            .slice(0, visibleCount)
            .map((promotion, cloneIndex) => ({
                ...promotion,
                carouselKey: `${promotion.id}-clone-${cloneIndex}`,
            }));

        return [...base, ...trailingClones];
    }, [canRotate, promotions, visibleCount]);

    useEffect(() => {
        setSlideIndex(0);
        isAnimatingRef.current = false;
        setAnimate(true);
    }, [promotions, visibleCount]);

    useEffect(() => {
        const measureLayout = (): void => {
            const container = trackRef.current;
            const slide =
                container?.querySelector<HTMLElement>(
                    '[data-promotion-slide]',
                );

            if (!container || !slide) {
                return;
            }

            const gap = 16;
            const slideWidth = slide.getBoundingClientRect().width;

            if (slideWidth <= 0) {
                return;
            }

            const step = slideWidth + gap;

            setSlideStepPx(step);
            setVisibleCount(
                Math.max(1, Math.floor((container.clientWidth + gap) / step)),
            );
        };

        measureLayout();

        const node = trackRef.current;

        if (!node) {
            return;
        }

        const observer = new ResizeObserver(measureLayout);
        observer.observe(node);
        window.addEventListener('resize', measureLayout);

        return () => {
            observer.disconnect();
            window.removeEventListener('resize', measureLayout);
        };
    }, [slides.length]);

    const snapToStart = useCallback(() => {
        setAnimate(false);
        setSlideIndex(0);

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                setAnimate(true);
                isAnimatingRef.current = false;
            });
        });
    }, []);

    const goNext = useCallback(() => {
        if (
            isAnimatingRef.current ||
            slideStepPx === 0 ||
            !canRotate
        ) {
            return;
        }

        isAnimatingRef.current = true;
        setAnimate(true);
        setSlideIndex((current) => current + 1);
    }, [canRotate, slideStepPx]);

    const goPrevious = useCallback(() => {
        if (
            isAnimatingRef.current ||
            slideStepPx === 0 ||
            !canRotate
        ) {
            return;
        }

        isAnimatingRef.current = true;

        if (slideIndex === 0) {
            setAnimate(false);
            setSlideIndex(promotions.length);

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    setAnimate(true);
                    setSlideIndex(promotions.length - 1);
                });
            });

            return;
        }

        setAnimate(true);
        setSlideIndex((current) => current - 1);
    }, [canRotate, promotions.length, slideIndex, slideStepPx]);

    const handleTransitionEnd = useCallback(
        (event: TransitionEvent<HTMLDivElement>) => {
            if (event.propertyName !== 'transform') {
                return;
            }

            if (slideIndex >= promotions.length) {
                snapToStart();

                return;
            }

            isAnimatingRef.current = false;
        },
        [promotions.length, slideIndex, snapToStart],
    );

    const goToPromotion = useCallback(
        (promotionId: string) => {
            if (isAnimatingRef.current || !canRotate) {
                return;
            }

            const targetIndex = promotions.findIndex(
                (promotion) => promotion.id === promotionId,
            );

            if (targetIndex < 0 || targetIndex === slideIndex) {
                return;
            }

            isAnimatingRef.current = true;
            setAnimate(true);
            setSlideIndex(targetIndex);
        },
        [canRotate, promotions, slideIndex],
    );

    useCarouselAutoAdvance(canRotate, isPaused, goNext, PROMOTION_AUTO_ADVANCE_MS);

    const activePromotionId =
        promotions[slideIndex % promotions.length]?.id ??
        promotions[0]?.id;

    if (promotions.length === 0) {
        return (
            <p className="hidden text-sm text-muted-foreground md:block">
                No hay promociones activas por ahora.
            </p>
        );
    }

    return (
        <div
            className={cn('relative hidden space-y-3 md:block', className)}
            aria-roledescription="carrusel"
            aria-label="Promociones"
            {...pauseHandlers}
        >
            {canRotate ? (
                <>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="absolute top-1/2 -left-2 z-10 hidden size-9 -translate-y-1/2 rounded-full bg-surface shadow-sm lg:flex"
                        aria-label="Promoción anterior"
                        onClick={goPrevious}
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="absolute top-1/2 -right-2 z-10 hidden size-9 -translate-y-1/2 rounded-full bg-surface shadow-sm lg:flex"
                        aria-label="Promoción siguiente"
                        onClick={goNext}
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                </>
            ) : null}

            <div className="overflow-hidden" ref={trackRef}>
                <div
                    className={cn(
                        'flex gap-4',
                        animate && 'transition-transform duration-500 ease-out',
                    )}
                    style={{
                        transform: `translateX(-${slideIndex * slideStepPx}px)`,
                    }}
                    onTransitionEnd={handleTransitionEnd}
                >
                    {slides.map((promotion) => (
                        <div
                            key={promotion.carouselKey}
                            data-promotion-slide
                            className="w-[calc((100%-1rem)/2)] shrink-0 lg:w-[calc((100%-2rem)/3)]"
                        >
                            <MobilePromotionCard
                                promotion={promotion}
                                variant={cardVariant}
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
                    {promotions.map((promotion) => (
                        <button
                            key={promotion.id}
                            type="button"
                            aria-label={`Ir a promoción ${promotion.name}`}
                            aria-current={
                                promotion.id === activePromotionId
                                    ? 'true'
                                    : undefined
                            }
                            className={cn(
                                'size-2 rounded-full transition-colors',
                                promotion.id === activePromotionId
                                    ? 'bg-navy'
                                    : 'bg-border hover:bg-muted-foreground/40',
                            )}
                            onClick={() => goToPromotion(promotion.id)}
                        />
                    ))}
                </div>
            ) : null}
        </div>
    );
}
