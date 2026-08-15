import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';
import { formatDistanceKm } from '@/lib/maps/google-maps-url';
import { cn } from '@/lib/utils';
import {
    accept,
    reject,
} from '@/routes/driver/orders';

export type DriverAvailableOrder = {
    id: number;
    order_number: string;
    business_status_label: string;
    estimated_preparation_minutes?: number | null;
    service_fee: string;
    restaurant: {
        name?: string | null;
        branch_name?: string | null;
    };
    delivery_address?: {
        address_text: string;
    } | null;
    is_custom?: boolean;
    distance_to_pickup_meters?: number | null;
};

type AvailableOrderCardProps = {
    order: DriverAvailableOrder;
    className?: string;
};

export function AvailableOrderCard({
    order,
    className,
}: AvailableOrderCardProps) {
    return (
        <article
            className={cn(
                'rounded-xl border border-border bg-surface p-4 shadow-sm',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h3 className="text-base font-semibold text-navy">
                        {order.restaurant.name}
                    </h3>
                    <p className="text-xs text-muted-foreground">
                        #{order.order_number}
                        {order.restaurant.branch_name
                            ? ` · ${order.restaurant.branch_name}`
                            : ''}
                        {order.is_custom ? ' · Personalizado' : ''}
                    </p>
                </div>
                <p className="text-base font-semibold text-primary">
                    {formatMoney(order.service_fee)}
                </p>
            </div>

            <dl className="mt-3 grid grid-cols-2 gap-3 text-sm">
                <div>
                    <dt className="text-muted-foreground">Estado</dt>
                    <dd className="font-medium text-navy">
                        {order.business_status_label}
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Prep.</dt>
                    <dd className="font-medium text-navy">
                        {order.estimated_preparation_minutes
                            ? `${order.estimated_preparation_minutes} min`
                            : '—'}
                    </dd>
                </div>
                <div className="col-span-2">
                    <dt className="text-muted-foreground">Destino</dt>
                    <dd className="font-medium text-navy">
                        {order.delivery_address?.address_text ?? 'Sin dirección'}
                    </dd>
                </div>
                {typeof order.distance_to_pickup_meters === 'number' ? (
                    <div className="col-span-2">
                        <dt className="text-muted-foreground">Distancia</dt>
                        <dd className="font-medium text-navy">
                            A {formatDistanceKm(order.distance_to_pickup_meters)} del
                            establecimiento
                        </dd>
                    </div>
                ) : null}
            </dl>

            <div className="mt-4 flex flex-col gap-2 sm:flex-row">
                <Button
                    type="button"
                    variant="outline"
                    className="min-h-12 flex-1"
                    onClick={() =>
                        router.post(reject.url(order.order_number))
                    }
                >
                    Rechazar
                </Button>
                <Button
                    type="button"
                    className="min-h-12 flex-1"
                    onClick={() =>
                        router.post(accept.url(order.order_number))
                    }
                >
                    Aceptar
                </Button>
            </div>
        </article>
    );
}
