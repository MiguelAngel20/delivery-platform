import type { ReactNode } from 'react';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type ProcessingOverlayProps = {
    open: boolean;
    title?: string;
    description?: string;
    className?: string;
    children?: ReactNode;
};

export function ProcessingOverlay({
    open,
    title = 'Procesando…',
    description,
    className,
    children,
}: ProcessingOverlayProps) {
    if (!open) {
        return null;
    }

    return (
        <div
            role="alert"
            aria-busy="true"
            aria-live="assertive"
            className={cn(
                'fixed inset-0 z-[100] flex items-center justify-center bg-navy/40 p-6 backdrop-blur-[2px]',
                className,
            )}
        >
            <div className="w-full max-w-sm rounded-2xl border border-border bg-background px-6 py-8 text-center shadow-xl">
                {children ?? (
                    <>
                        <Spinner className="mx-auto size-10 text-primary" />
                        <p className="mt-4 text-lg font-semibold text-navy dark:text-white">
                            {title}
                        </p>
                        {description ? (
                            <p className="mt-2 text-sm text-muted-foreground dark:text-white/80">
                                {description}
                            </p>
                        ) : null}
                    </>
                )}
            </div>
        </div>
    );
}
