import {
    useEffect,
    useRef,
    useState,
    type MouseEvent as ReactMouseEvent,
    type PointerEvent as ReactPointerEvent,
} from 'react';

export const PROMOTION_AUTO_ADVANCE_MS = 4000;

const SWIPE_THRESHOLD_PX = 48;

export function useCarouselSwipe(
    enabled: boolean,
    onNext: () => void,
    onPrevious: () => void,
): {
    interacting: boolean;
    pointerHandlers: {
        onPointerDown?: (event: ReactPointerEvent<HTMLElement>) => void;
        onPointerMove?: (event: ReactPointerEvent<HTMLElement>) => void;
        onPointerUp?: (event: ReactPointerEvent<HTMLElement>) => void;
        onPointerCancel?: (event: ReactPointerEvent<HTMLElement>) => void;
        onClickCapture?: (event: ReactMouseEvent<HTMLElement>) => void;
    };
} {
    const [interacting, setInteracting] = useState(false);
    const startX = useRef<number | null>(null);
    const startY = useRef<number | null>(null);
    const axis = useRef<'x' | 'y' | null>(null);
    const deltaX = useRef(0);
    const suppressClick = useRef(false);
    const onNextRef = useRef(onNext);
    const onPreviousRef = useRef(onPrevious);

    onNextRef.current = onNext;
    onPreviousRef.current = onPrevious;

    const resetGesture = (): void => {
        startX.current = null;
        startY.current = null;
        axis.current = null;
        deltaX.current = 0;
        setInteracting(false);
    };

    if (!enabled) {
        return { interacting: false, pointerHandlers: {} };
    }

    const onPointerDown = (event: ReactPointerEvent<HTMLElement>): void => {
        if (event.button !== 0) {
            return;
        }

        startX.current = event.clientX;
        startY.current = event.clientY;
        axis.current = null;
        deltaX.current = 0;
        suppressClick.current = false;
    };

    const onPointerMove = (event: ReactPointerEvent<HTMLElement>): void => {
        if (startX.current === null || startY.current === null) {
            return;
        }

        const dx = event.clientX - startX.current;
        const dy = event.clientY - startY.current;

        if (axis.current === null) {
            if (Math.abs(dx) < 8 && Math.abs(dy) < 8) {
                return;
            }

            axis.current = Math.abs(dx) > Math.abs(dy) ? 'x' : 'y';
        }

        if (axis.current !== 'x') {
            return;
        }

        deltaX.current = dx;
        setInteracting(true);

        if (!event.currentTarget.hasPointerCapture(event.pointerId)) {
            event.currentTarget.setPointerCapture(event.pointerId);
        }
    };

    const finish = (event: ReactPointerEvent<HTMLElement>): void => {
        if (startX.current === null) {
            return;
        }

        const delta = deltaX.current;
        const horizontal = axis.current === 'x';

        if (event.currentTarget.hasPointerCapture(event.pointerId)) {
            event.currentTarget.releasePointerCapture(event.pointerId);
        }

        resetGesture();

        if (!horizontal || Math.abs(delta) < SWIPE_THRESHOLD_PX) {
            return;
        }

        suppressClick.current = true;

        if (delta < 0) {
            onNextRef.current();

            return;
        }

        onPreviousRef.current();
    };

    const onClickCapture = (event: ReactMouseEvent<HTMLElement>): void => {
        if (!suppressClick.current) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        suppressClick.current = false;
    };

    return {
        interacting,
        pointerHandlers: {
            onPointerDown,
            onPointerMove,
            onPointerUp: finish,
            onPointerCancel: finish,
            onClickCapture,
        },
    };
}

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
