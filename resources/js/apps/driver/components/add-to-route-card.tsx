import type { DriverAvailableOrder } from '@/apps/driver/components/available-order-card';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { accept, reject } from '@/routes/driver/orders';

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
            <div className="mt-3 flex items-center justify-between text-sm">
                <span className="text-muted-foreground">Tarifa de servicio</span>
                <span className="font-semibold text-primary">
                    {formatMoney(order.service_fee)}
                </span>
            </div>
            <div className="mt-4 flex flex-col gap-2 sm:flex-row">
                <Button
                    type="button"
                    variant="outline"
                    className="min-h-12 flex-1 bg-surface"
                    onClick={() => router.post(reject.url(order.order_number))}
                >
                    Ignorar
                </Button>
                <Button
                    type="button"
                    className="min-h-12 flex-1"
                    onClick={() => router.post(accept.url(order.order_number))}
                >
                    Agregar a mi ruta
                </Button>
            </div>
        </article>
    );
}
