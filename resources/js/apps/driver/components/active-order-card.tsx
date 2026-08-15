import { router } from '@inertiajs/react';
import { useState } from 'react';
import { StatusBadge } from '@/components/data-display/status-badge';
import type { StatusTone } from '@/components/data-display/status-badge';
import { OrderActionDialog } from '@/components/orders/order-action-dialog';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import {
    arrive,
    cannotContinue,
    deliver,
    pickup,
    startDelivery,
} from '@/routes/driver/orders';
import { store as storeIncident } from '@/routes/driver/orders/incidents';

type Option = { value: string; label: string };

export type DriverActiveOrder = {
    id: number;
    order_number: string;
    order_status: string;
    business_status_label: string;
    service_fee: string;
    restaurant: {
        name?: string | null;
        branch_name?: string | null;
    };
    is_custom?: boolean;
    customer: {
        name?: string | null;
        public_label?: string | null;
        verified?: boolean;
        completed_orders?: number;
        is_frequent?: boolean;
    };
    pickup_address?: {
        address_text: string;
        latitude?: string | null;
        longitude?: string | null;
        google_maps_url?: string | null;
    } | null;
    delivery_address?: {
        address_text: string;
        latitude?: string | null;
        longitude?: string | null;
        google_maps_url?: string | null;
    } | null;
    actions: {
        arrive: boolean;
        pickup: boolean;
        start_delivery: boolean;
        deliver: boolean;
        cannot_continue?: boolean;
        report_problem?: boolean;
    };
    cannot_continue_reasons?: Option[];
    incident_types?: Option[];
};

const statusTone: Record<string, StatusTone> = {
    driver_assigned: 'primary',
    driver_at_business: 'warning',
    ready_for_pickup: 'warning',
    picked_up: 'info',
    on_the_way: 'info',
};

type ActiveOrderCardProps = {
    order: DriverActiveOrder;
    className?: string;
};

export function ActiveOrderCard({ order, className }: ActiveOrderCardProps) {
    const [dialog, setDialog] = useState<'cannot' | 'report' | null>(null);
    const pickupUrl = order.pickup_address?.google_maps_url ?? null;
    const deliveryUrl = order.delivery_address?.google_maps_url ?? null;

    const primaryAction = order.actions.arrive
        ? {
              label: 'Llegué al establecimiento',
              run: () => router.post(arrive.url(order.order_number)),
          }
        : order.actions.pickup
          ? {
                label: 'Pedido recogido',
                run: () => router.post(pickup.url(order.order_number)),
            }
          : order.actions.start_delivery
            ? {
                  label: 'Iniciar entrega',
                  run: () => router.post(startDelivery.url(order.order_number)),
              }
            : order.actions.deliver
              ? {
                    label: 'Pedido entregado',
                    run: () => router.post(deliver.url(order.order_number)),
                }
              : null;

    return (
        <article
            className={cn(
                'rounded-xl border border-primary/30 bg-surface p-4 shadow-sm',
                className,
            )}
        >
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Pedido activo
                    </p>
                    <h3 className="text-lg font-semibold text-navy">
                        #{order.order_number}
                    </h3>
                </div>
                <div className="flex flex-wrap justify-end gap-2">
                    {order.is_custom ? (
                        <StatusBadge tone="neutral">Pedido personalizado</StatusBadge>
                    ) : null}
                    <StatusBadge
                        tone={statusTone[order.order_status] ?? 'neutral'}
                    >
                        {order.business_status_label}
                    </StatusBadge>
                </div>
            </div>

            <dl className="mt-4 space-y-3 text-sm">
                <div>
                    <dt className="text-muted-foreground">Empresa</dt>
                    <dd className="font-medium text-navy">
                        {order.restaurant.name}
                        {order.restaurant.branch_name
                            ? ` · ${order.restaurant.branch_name}`
                            : ''}
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Cliente</dt>
                    <dd className="font-medium text-navy">
                        {order.customer.name ?? 'Cliente'}
                    </dd>
                    <dd className="mt-1 text-xs text-muted-foreground">
                        {order.customer.public_label ??
                            (order.customer.verified
                                ? 'Cuenta verificada'
                                : 'Cuenta nueva')}
                        {' · '}
                        {order.customer.completed_orders ?? 0} pedidos
                        completados
                        {order.customer.is_frequent
                            ? ' · Cliente frecuente'
                            : ''}
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Recogida</dt>
                    <dd className="font-medium text-navy">
                        {order.pickup_address?.address_text ?? '—'}
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Entrega</dt>
                    <dd className="font-medium text-navy">
                        {order.delivery_address?.address_text ?? '—'}
                    </dd>
                </div>
                <div className="flex items-center justify-between border-t border-border pt-3">
                    <dt className="text-muted-foreground">Tarifa de servicio</dt>
                    <dd className="text-base font-semibold text-primary">
                        {formatMoney(order.service_fee)}
                    </dd>
                </div>
            </dl>

            <div className="mt-4 flex flex-col gap-2">
                {pickupUrl ? (
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-12"
                        asChild
                    >
                        <a href={pickupUrl} target="_blank" rel="noreferrer">
                            Abrir recogida
                        </a>
                    </Button>
                ) : null}
                {deliveryUrl ? (
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-12"
                        asChild
                    >
                        <a href={deliveryUrl} target="_blank" rel="noreferrer">
                            Abrir entrega
                        </a>
                    </Button>
                ) : null}
                {primaryAction ? (
                    <Button
                        type="button"
                        className="min-h-12"
                        onClick={primaryAction.run}
                    >
                        {primaryAction.label}
                    </Button>
                ) : null}
                {order.actions.cannot_continue ? (
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-12"
                        onClick={() => setDialog('cannot')}
                    >
                        No puedo continuar
                    </Button>
                ) : null}
                {order.actions.report_problem ? (
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-12"
                        onClick={() => setDialog('report')}
                    >
                        Reportar problema
                    </Button>
                ) : null}
            </div>

            <OrderActionDialog
                open={dialog === 'cannot'}
                onOpenChange={(open) => setDialog(open ? 'cannot' : null)}
                title="No puedo continuar"
                description="Describe el problema. Si aún no recogiste el pedido, se liberará para otro repartidor."
                actionUrl={cannotContinue.url(order.order_number)}
                codeField="reason_code"
                options={order.cannot_continue_reasons ?? []}
                selectLabel="Motivo"
                notesLabel="Descripción"
                notesName="description"
                submitLabel="Enviar"
            />
            <OrderActionDialog
                open={dialog === 'report'}
                onOpenChange={(open) => setDialog(open ? 'report' : null)}
                title="Reportar problema"
                description="Quedará registrado para revisión administrativa. El pedido no se cancela."
                actionUrl={storeIncident.url(order.order_number)}
                codeField="type"
                options={order.incident_types ?? []}
                selectLabel="Tipo"
                notesLabel="Descripción"
                notesName="description"
                submitLabel="Enviar reporte"
            />
        </article>
    );
}
