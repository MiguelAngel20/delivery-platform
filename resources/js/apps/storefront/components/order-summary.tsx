import { formatMoney } from '@/apps/storefront/mocks';
import { cn } from '@/lib/utils';

type OrderSummaryProps = {
    subtotal: number;
    service: number;
    discount: number;
    className?: string;
};

export function OrderSummary({
    subtotal,
    service,
    discount,
    className,
}: OrderSummaryProps) {
    return (
        <dl
            className={cn(
                'space-y-2 rounded-xl border border-border bg-surface p-4 text-sm',
                className,
            )}
        >
            <div className="flex justify-between gap-3">
                <dt className="text-muted-foreground">Subtotal</dt>
                <dd className="font-medium text-navy">
                    {formatMoney(subtotal)}
                </dd>
            </div>
            <div className="flex justify-between gap-3">
                <dt className="text-muted-foreground">Servicio</dt>
                <dd className="font-medium text-navy">
                    {formatMoney(service)}
                </dd>
            </div>
            {discount > 0 ? (
                <div className="flex justify-between gap-3">
                    <dt className="text-muted-foreground">Descuento</dt>
                    <dd className="font-medium text-success">
                        -{formatMoney(discount)}
                    </dd>
                </div>
            ) : null}
        </dl>
    );
}
