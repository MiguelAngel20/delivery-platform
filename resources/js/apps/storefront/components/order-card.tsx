import { Link } from '@inertiajs/react';
import type { MockCustomerOrder } from '@/apps/storefront/mocks';
import { StatusBadge } from '@/components/data-display/status-badge';
import { cn } from '@/lib/utils';
import customer from '@/routes/customer';

type OrderCardProps = {
    order: MockCustomerOrder;
    className?: string;
};

export function OrderCard({ order, className }: OrderCardProps) {
    return (
        <Link
            href={customer.orders.show(order.id)}
            className={cn(
                'block rounded-xl border border-border bg-surface p-4 shadow-sm',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h3 className="font-semibold text-navy">#{order.code}</h3>
                    <p className="text-sm text-muted-foreground">
                        {order.restaurant}
                    </p>
                </div>
                <StatusBadge
                    tone={
                        order.status === 'delivered' ? 'success' : 'primary'
                    }
                >
                    {order.statusLabel}
                </StatusBadge>
            </div>
            <div className="mt-3 flex items-center justify-between text-sm">
                <span className="text-muted-foreground">
                    {order.eta ?? 'Completado'}
                </span>
                <span className="font-semibold text-navy">{order.total}</span>
            </div>
        </Link>
    );
}
