import { router } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import { useState } from 'react';
import {
    DriverOrderPaymentInfo,
} from '@/apps/driver/components/driver-order-payment-info';
import type { DriverOrderPayment } from '@/apps/driver/components/driver-order-payment-info';
import { StatusBadge } from '@/components/data-display/status-badge';
import type { StatusTone } from '@/components/data-display/status-badge';
import { OrderActionDialog } from '@/components/orders/order-action-dialog';
import { notify } from '@/components/feedback/toast';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
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
    driver_status_label?: string;
    service_fee: string;
    payment: DriverOrderPayment;
    restaurant: {
        name?: string | null;
        branch_name?: string | null;
    };
    is_custom?: boolean;
    customer: {
        name?: string | null;
        phone?: string | null;
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
    ready_for_pickup: 'success',
    picked_up: 'info',
    on_the_way: 'info',
};

type ActiveOrderCardProps = {
    order: DriverActiveOrder;
    className?: string;
};

function LocationButton({
    href,
    label,
}: {
    href: string | null;
    label: string;
}) {
    if (!href) {
        return null;
    }

    return (
        <Button
            type="button"
            variant="outline"
            size="icon"
            className="size-10 shrink-0"
            asChild
        >
            <a
                href={href}
                target="_blank"
                rel="noreferrer"
                aria-label={label}
            >
                <MapPin className="size-4" />
            </a>
        </Button>
    );
}

function CopyablePhone({ phone }: { phone: string }) {
    const [, copy] = useClipboard();

    return (
        <button
            type="button"
            className="mt-1 text-sm font-medium text-primary hover:underline"
            onClick={() => {
                void copy(phone).then((copied) => {
                    if (copied) {
                        notify.success('Número copiado');
                    } else {
                        notify.error('No se pudo copiar el número');
                    }
                });
            }}
        >
            {phone}
        </button>
    );
}

export function ActiveOrderCard({ order, className }: ActiveOrderCardProps) {
    const [dialog, setDialog] = useState<'cannot' | 'report' | null>(null);
    const pickupUrl = order.pickup_address?.google_maps_url ?? null;
    const deliveryUrl = order.delivery_address?.google_maps_url ?? null;
    const businessLabel = [order.restaurant.name, order.restaurant.branch_name]
        .filter(Boolean)
        .join(' · ');
    const statusLabel =
        order.driver_status_label ?? order.business_status_label;

    const primaryAction = order.actions.arrive
        ? {
              label: 'Llegué al establecimiento',
              run: () => router.post(arrive.url(order.order_number)),
          }
        : order.actions.pickup
          ? {
                label: 'En camino',
                run: () => router.post(pickup.url(order.order_number)),
            }
          : order.actions.start_delivery
            ? {
                  label: 'Ya estoy afuera de su domicilio',
                  run: () =>
                      router.post(startDelivery.url(order.order_number)),
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
                <h3 className="text-lg font-semibold text-navy">
                    #{order.order_number}
                </h3>
                <div className="flex flex-wrap justify-end gap-2">
                    {order.is_custom ? (
                        <StatusBadge tone="neutral">Pedido personalizado</StatusBadge>
                    ) : null}
                    <StatusBadge
                        tone={
                            statusLabel === 'Listo para recoger'
                                ? 'success'
                                : (statusTone[order.order_status] ?? 'neutral')
                        }
                    >
                        {statusLabel}
                    </StatusBadge>
                </div>
            </div>

            <div className="mt-4 space-y-4 text-sm">
                <section className="space-y-1">
                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Datos del negocio
                    </p>
                    <div className="flex items-start gap-2">
                        <div className="min-w-0 flex-1">
                            <p className="font-medium text-navy">
                                {businessLabel || '—'}
                            </p>
                            <p className="mt-1 text-muted-foreground">
                                {order.pickup_address?.address_text ?? '—'}
                            </p>
                        </div>
                        <LocationButton
                            href={pickupUrl}
                            label="Abrir ubicación del negocio"
                        />
                    </div>
                </section>

                <div className="space-y-1" aria-hidden="true">
                    <div className="h-px bg-muted-foreground/20" />
                    <div className="h-px bg-muted-foreground/20" />
                </div>

                <section className="space-y-1">
                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Datos del cliente
                    </p>
                    <div className="flex items-start gap-2">
                        <div className="min-w-0 flex-1">
                            <p className="font-medium text-navy">
                                {order.customer.name ?? 'Cliente'}
                            </p>
                            {order.customer.phone ? (
                                <CopyablePhone phone={order.customer.phone} />
                            ) : null}
                            <p className="mt-1 text-muted-foreground">
                                {order.delivery_address?.address_text ?? '—'}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
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
                            </p>
                        </div>
                        <LocationButton
                            href={deliveryUrl}
                            label="Abrir ubicación de entrega"
                        />
                    </div>
                </section>

                <div className="border-t border-border pt-3">
                    <DriverOrderPaymentInfo payment={order.payment} />
                </div>
            </div>

            <div className="mt-4 flex flex-col gap-2">
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
