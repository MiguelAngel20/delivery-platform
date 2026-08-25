import { cn } from '@/lib/utils';

type MapCenterPinProps = {
    className?: string;
};

/**
 * Classic map pin for fixed center overlays (tip aligns to map center).
 */
export function MapCenterPin({ className }: MapCenterPinProps) {
    return (
        <svg
            viewBox="0 0 20 28"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            className={cn(
                'h-7 w-5 text-primary drop-shadow-[0_2px_3px_rgba(15,23,42,0.28)]',
                className,
            )}
            aria-hidden
        >
            <path
                d="M10 1C5.029 1 1 5.029 1 10c0 6.75 9 17 9 17s9-10.25 9-17c0-4.971-4.029-9-9-9Z"
                fill="currentColor"
                stroke="rgba(15, 23, 42, 0.18)"
                strokeWidth="0.75"
            />
            <circle cx="10" cy="10" r="2.75" fill="white" />
        </svg>
    );
}

export function createMapPinElement(): HTMLDivElement {
    const wrapper = document.createElement('div');
    wrapper.style.setProperty('--anchor-x', '50%');
    wrapper.style.setProperty('--anchor-y', '100%');
    wrapper.innerHTML =
        '<svg width="20" height="28" viewBox="0 0 20 28" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="filter:drop-shadow(0 2px 3px rgba(15,23,42,0.28))"><path d="M10 1C5.029 1 1 5.029 1 10c0 6.75 9 17 9 17s9-10.25 9-17c0-4.971-4.029-9-9-9Z" fill="var(--primary)" stroke="rgba(15, 23, 42, 0.18)" stroke-width="0.75"/><circle cx="10" cy="10" r="2.75" fill="white"/></svg>';

    return wrapper;
}
