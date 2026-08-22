import type { ReactNode } from 'react';
import { formatMoney } from '@/apps/storefront/mocks';
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
    return (
        <div
            className={cn(
                'sticky bottom-0 z-10 -mx-4 border-t border-border bg-background/95 px-4 py-4 backdrop-blur supports-[backdrop-filter]:bg-background/80 md:-mx-6 md:px-6',
                className,
            )}
        >
            {extra}
            <div className="mb-3 flex items-center justify-between gap-3">
                <span className="text-sm text-muted-foreground">Total</span>
                <span className="text-xl font-bold text-navy">
                    {formatMoney(total)}
                </span>
            </div>
            <div className="flex gap-3">
                {onBack ? (
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-12 flex-1"
                        onClick={onBack}
                        disabled={primaryLoading}
                    >
                        {backLabel}
                    </Button>
                ) : null}
                <Button
                    type="button"
                    className={cn(
                        'min-h-12',
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
    );
}
