import type { ReactNode } from 'react';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

export type LoadingDialogProps = {
    open: boolean;
    /** Short headline shown under the spinner. */
    title?: string;
    /** Supporting copy (e.g. “No cierres esta ventana…”). */
    description?: string;
    className?: string;
    /** Optional custom body; replaces spinner + title + description. */
    children?: ReactNode;
};

/**
 * Full-screen blocking loader for slow actions (geolocation, checkout, etc.).
 * Reuse across the app and only change `title` / `description`.
 */
export function LoadingDialog({
    open,
    title = 'Cargando…',
    description,
    className,
    children,
}: LoadingDialogProps) {
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
                            <p className="mt-2 text-sm leading-relaxed text-muted-foreground dark:text-white/80">
                                {description}
                            </p>
                        ) : null}
                    </>
                )}
            </div>
        </div>
    );
}
