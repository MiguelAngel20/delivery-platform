import type { ReactNode } from 'react';
import { formatMoney } from '@/apps/storefront/mocks';
import { useStorefrontShell } from '@/apps/storefront/hooks/use-storefront-shell';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type CheckoutFooterProps = {
    total: number;
    primaryLabel: string;
    onPrimary: () => void;
    primaryDisabled?: boolean;
    primaryLoading?: boolean;
    onBack?: () => void;
    backLabel?: string;
    className?: string;
    extra?: ReactNode;
};

function scrollCheckoutToTop(): void {
    window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
}

export function CheckoutFooter({
    total,
    primaryLabel,
    onPrimary,
    primaryDisabled = false,
    primaryLoading = false,
    onBack,
    backLabel = 'Atrás',
    className,
    extra,
}: CheckoutFooterProps) {
    const { showBottomNav } = useStorefrontShell();

    return (
        <div
            className={cn(
                'border-t border-border bg-background',
                'fixed inset-x-0 z-20 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80',
                showBottomNav ? 'bottom-16' : 'bottom-0',
                'md:static md:z-auto md:rounded-xl md:border md:bg-surface md:shadow-sm md:backdrop-blur-none',
                className,
            )}
        >
            <div className="mx-auto w-full max-w-6xl px-4 py-3 md:px-5 md:py-4">
                {extra}
                <div className="md:flex md:items-center md:justify-between md:gap-6">
                    <div className="mb-2 flex items-center justify-between gap-3 md:mb-0">
                        <span className="text-sm text-muted-foreground">
                            Total
                        </span>
                        <span className="text-lg font-bold text-navy md:text-xl">
                            {formatMoney(total)}
                        </span>
                    </div>
                    <div className="flex gap-2.5 md:flex-shrink-0 md:justify-end md:gap-3">
                        {onBack ? (
                            <Button
                                type="button"
                                variant="outline"
                                className="min-h-11 flex-1 md:min-h-12 md:flex-none md:px-6"
                                onClick={() => {
                                    scrollCheckoutToTop();
                                    onBack();
                                }}
                                disabled={primaryLoading}
                            >
                                {backLabel}
                            </Button>
                        ) : null}
                        <Button
                            type="button"
                            className={cn(
                                'min-h-11 md:min-h-12',
                                onBack
                                    ? 'flex-[2] md:flex-none md:px-8'
                                    : 'w-full md:w-auto md:min-w-48 md:px-8',
                            )}
                            onClick={() => {
                                scrollCheckoutToTop();
                                onPrimary();
                            }}
                            disabled={primaryDisabled || primaryLoading}
                            loading={primaryLoading}
                        >
                            {primaryLoading
                                ? 'Procesando pedido…'
                                : primaryLabel}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}
