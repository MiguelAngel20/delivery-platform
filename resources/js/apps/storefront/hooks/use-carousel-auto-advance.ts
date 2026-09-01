import { useEffect, useRef, useState } from 'react';

export const PROMOTION_AUTO_ADVANCE_MS = 4500;

export function useCarouselPauseHandlers(): {
    isPaused: boolean;
    pauseHandlers: {
        onMouseEnter: () => void;
        onMouseLeave: () => void;
        onTouchStart: () => void;
        onTouchEnd: () => void;
        onTouchCancel: () => void;
    };
} {
    const [isPaused, setIsPaused] = useState(false);

    return {
        isPaused,
        pauseHandlers: {
            onMouseEnter: () => setIsPaused(true),
            onMouseLeave: () => setIsPaused(false),
            onTouchStart: () => setIsPaused(true),
            onTouchEnd: () => setIsPaused(false),
            onTouchCancel: () => setIsPaused(false),
        },
    };
}

export function useCarouselAutoAdvance(
    enabled: boolean,
    isPaused: boolean,
    onTick: () => void,
    intervalMs: number = PROMOTION_AUTO_ADVANCE_MS,
): void {
    const onTickRef = useRef(onTick);
    onTickRef.current = onTick;

    useEffect(() => {
        if (!enabled || isPaused) {
            return;
        }

        const interval = window.setInterval(() => {
            onTickRef.current();
        }, intervalMs);

        return () => window.clearInterval(interval);
    }, [enabled, isPaused, intervalMs]);
}
