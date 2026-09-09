import { Link } from '@inertiajs/react';
import { StatusBadge } from '@/components/data-display/status-badge';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { show } from '@/routes/driver/orders';

export type DriverCompletedOrder = {
    id: number;
    order_number: string;
    business_name: string;
    branch_name?: string | null;
    delivered_at: string | null;
    driver_earning: string;
    status_label: string;
    has_rating?: boolean;
    overall_rating?: number | null;
};

type CompletedOrderCardProps = {
    order: DriverCompletedOrder;
    className?: string;
};

function formatDeliveredAt(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('es-MX', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

export function CompletedOrderCard({
    order,
    className,
}: CompletedOrderCardProps) {
    return (
        <article
            className={cn(
                'rounded-xl border border-border bg-surface p-4 shadow-sm',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 space-y-1">
                    <h3 className="font-semibold text-navy">
                        #{order.order_number}
                    </h3>
                    <p className="truncate text-sm text-muted-foreground">
                        {order.business_name}
                        {order.branch_name ? ` · ${order.branch_name}` : ''}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {formatDeliveredAt(order.delivered_at)}
                    </p>
                </div>
                <div className="flex shrink-0 flex-col items-end gap-2">
                    <StatusBadge tone="success">{order.status_label}</StatusBadge>
                    <span className="text-sm font-semibold text-success">
                        {formatMoney(order.driver_earning)}
                    </span>
                </div>
            </div>

            {order.has_rating && order.overall_rating ? (
                <p className="mt-3 text-sm text-muted-foreground">
                    Calificación:{' '}
                    <span className="font-medium text-navy">
                        {order.overall_rating} ★
                    </span>
                </p>
            ) : null}

            <Button
                variant="outline"
                className="mt-4 min-h-12 w-full"
                asChild
            >
                <Link href={show.url(order.id)}>Ver detalles</Link>
            </Button>
        </article>
    );
}
