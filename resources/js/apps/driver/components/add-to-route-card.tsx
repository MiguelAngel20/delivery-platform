import type { DriverAvailableOrder } from '@/apps/driver/components/available-order-card';
import { router } from '@inertiajs/react';
import { DriverOrderPaymentInfo } from '@/apps/driver/components/driver-order-payment-info';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { accept } from '@/routes/driver/orders';

type AddToRouteCardProps = {
    order: DriverAvailableOrder;
    className?: string;
};

export function AddToRouteCard({ order, className }: AddToRouteCardProps) {
    return (
        <article
            className={cn(
                'rounded-xl border border-dashed border-primary/40 bg-primary/5 p-4',
                className,
            )}
        >
            <p className="text-xs font-medium uppercase tracking-wide text-primary">
                Agregar a tu ruta
            </p>
            <h3 className="mt-1 text-base font-semibold text-navy">
                Nuevo pedido del mismo establecimiento
            </h3>
            <p className="mt-1 text-sm text-muted-foreground">
                {order.restaurant.name}
                {order.restaurant.branch_name
                    ? ` · ${order.restaurant.branch_name}`
                    : ''}
            </p>
            <div className="mt-4 border-t border-border pt-4">
                <DriverOrderPaymentInfo payment={order.payment} compact />
            </div>
            <div className="mt-4">
                <Button
                    type="button"
                    className="min-h-12 w-full"
                    onClick={() => router.post(accept.url(order.order_number))}
                >
                    Agregar a mi ruta
                </Button>
            </div>
        </article>
    );
}
