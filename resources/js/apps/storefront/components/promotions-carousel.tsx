import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { PromotionCard } from '@/apps/storefront/components/promotion-card';
import type { MockPromotion } from '@/apps/storefront/mocks';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type PromotionsCarouselProps = {
    promotions: MockPromotion[];
    className?: string;
};

const DESKTOP_VISIBLE = 5;
const AUTO_ADVANCE_MS = 4500;

export function PromotionsCarousel({
    promotions,
    className,
}: PromotionsCarouselProps) {
    const scrollerRef = useRef<HTMLDivElement>(null);
    const [canScroll, setCanScroll] = useState(false);

    const updateScrollState = (): void => {
        const node = scrollerRef.current;

        if (!node) {
            return;
        }

        setCanScroll(node.scrollWidth > node.clientWidth + 4);
    };

    useEffect(() => {
        updateScrollState();
        window.addEventListener('resize', updateScrollState);

        return () => window.removeEventListener('resize', updateScrollState);
    }, [promotions.length]);

    const scrollByCard = (direction: 1 | -1): void => {
        const node = scrollerRef.current;

        if (!node) {
            return;
        }

        const card = node.querySelector<HTMLElement>('[data-promotion-slide]');
        const step = card?.offsetWidth ?? node.clientWidth / DESKTOP_VISIBLE;
        const gap = 16;
        const delta = (step + gap) * direction;
        const maxScroll = node.scrollWidth - node.clientWidth;
        let next = node.scrollLeft + delta;

        if (direction === 1 && next >= maxScroll - 2) {
            next = 0;
        } else if (direction === -1 && next <= 2) {
            next = maxScroll;
        }

        node.scrollTo({ left: next, behavior: 'smooth' });
    };

    useEffect(() => {
        if (promotions.length <= DESKTOP_VISIBLE) {
            return;
        }

        const interval = window.setInterval(() => {
            scrollByCard(1);
        }, AUTO_ADVANCE_MS);

        return () => window.clearInterval(interval);
    }, [promotions.length]);

    if (promotions.length === 0) {
        return (
            <p className="hidden text-sm text-muted-foreground md:block">
                No hay promociones activas por ahora.
            </p>
        );
    }

    return (
        <div className={cn('relative hidden md:block', className)}>
            {canScroll ? (
                <>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="absolute top-1/2 -left-2 z-10 hidden size-9 -translate-y-1/2 rounded-full bg-surface shadow-sm lg:flex"
                        aria-label="Promoción anterior"
                        onClick={() => scrollByCard(-1)}
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="absolute top-1/2 -right-2 z-10 hidden size-9 -translate-y-1/2 rounded-full bg-surface shadow-sm lg:flex"
                        aria-label="Promoción siguiente"
                        onClick={() => scrollByCard(1)}
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                </>
            ) : null}

            <div
                ref={scrollerRef}
                onScroll={updateScrollState}
                className="flex gap-4 overflow-x-auto scroll-smooth pb-1 snap-x snap-mandatory [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            >
                {promotions.map((promotion) => (
                    <div
                        key={promotion.id}
                        data-promotion-slide
                        className="w-[min(70%,14rem)] shrink-0 snap-start sm:w-[calc((100%-1rem)/2)] md:w-[calc((100%-2rem)/3)] lg:w-[calc((100%-4rem)/5)]"
                    >
                        <PromotionCard promotion={promotion} square />
                    </div>
                ))}
            </div>
        </div>
    );
}
