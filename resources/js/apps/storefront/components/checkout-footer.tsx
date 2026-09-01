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
                'fixed inset-x-0 z-20 border-t border-border bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80',
                showBottomNav ? 'bottom-16' : 'bottom-0',
                className,
            )}
        >
            <div className="mx-auto w-full max-w-6xl px-4 py-3 md:px-6 md:py-4">
                {extra}
                <div className="mb-2 flex items-center justify-between gap-3 md:mb-3">
                    <span className="text-sm text-muted-foreground">Total</span>
                    <span className="text-lg font-bold text-navy md:text-xl">
                        {formatMoney(total)}
                    </span>
                </div>
                <div className="flex gap-2.5 md:gap-3">
                    {onBack ? (
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-11 flex-1 md:min-h-12"
                            onClick={onBack}
                            disabled={primaryLoading}
                        >
                            {backLabel}
                        </Button>
                    ) : null}
                    <Button
                        type="button"
                        className={cn(
                            'min-h-11 md:min-h-12',
                            onBack ? 'flex-[2]' : 'w-full',
                        )}
                        onClick={onPrimary}
                        disabled={primaryDisabled || primaryLoading}
                        loading={primaryLoading}
                    >
                        {primaryLoading ? 'Procesando pedido…' : primaryLabel}
                    </Button>
                </div>
            </div>
        </div>
    );
}
