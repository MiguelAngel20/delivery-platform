import { MapPinOff } from 'lucide-react';
import { COVERAGE_UNAVAILABLE_MESSAGE } from '@/apps/storefront/lib/check-delivery-coverage';
import { cn } from '@/lib/utils';

type CoverageUnavailableBannerProps = {
    message?: string | null;
    className?: string;
};

export function CoverageUnavailableBanner({
    message = COVERAGE_UNAVAILABLE_MESSAGE,
    className,
}: CoverageUnavailableBannerProps) {
    if (!message) {
        return null;
    }

    return (
        <div
            role="alert"
            className={cn(
                'flex gap-3 rounded-xl border border-amber-300/80 bg-amber-50 px-4 py-3 text-sm text-amber-950',
                className,
            )}
        >
            <MapPinOff
                className="mt-0.5 size-4 shrink-0 text-amber-700"
                aria-hidden
            />
            <p className="leading-snug">{message}</p>
        </div>
    );
}
