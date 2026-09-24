import { useState } from 'react';
import { DriverOrderPaymentInfo } from '@/apps/driver/components/driver-order-payment-info';
import type { DriverOrderPayment } from '@/apps/driver/components/driver-order-payment-info';
import { StatusBadge } from '@/components/data-display/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatMoney } from '@/lib/money';
import { resolveRestaurantLabel } from '@/lib/restaurant-label';
import { cn } from '@/lib/utils';

export type DriverCompletedOrder = {
    id: number;
    order_number: string;
    business_name: string;
    branch_name?: string | null;
    delivered_at: string | null;
    driver_earning: string;
    gross_earning?: string;
    driver_commission?: string;
    status_label: string;
    has_rating?: boolean;
    overall_rating?: number | null;
    pickup_address?: {
        address_text: string;
    } | null;
    delivery_address?: {
        address_text: string;
    } | null;
    payment?: DriverOrderPayment;
    items?: Array<{
        id: number;
        line_label: string;
        subtotal: string;
        notes?: string | null;
    }>;
    notes?: string | null;
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
    const [open, setOpen] = useState(false);
    const businessLabel = resolveRestaurantLabel(
        order.business_name,
        order.branch_name,
        '—',
    );
    const commission = Number(order.driver_commission ?? 0);

    return (
        <>
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
                            {businessLabel}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {formatDeliveredAt(order.delivered_at)}
                        </p>
                    </div>
                    <div className="flex shrink-0 flex-col items-end gap-2">
                        <StatusBadge tone="success">
                            {order.status_label}
                        </StatusBadge>
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
                    type="button"
                    variant="outline"
                    className="mt-4 min-h-12 w-full"
                    onClick={() => setOpen(true)}
                >
                    Ver detalles
                </Button>
            </article>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-navy">
                            Pedido #{order.order_number}
                        </DialogTitle>
                        <DialogDescription>
                            Entregado {formatDeliveredAt(order.delivered_at)}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 text-sm">
                        <section className="space-y-1">
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Negocio
                            </p>
                            <p className="font-medium text-navy">
                                {businessLabel || '—'}
                            </p>
                            <p className="text-muted-foreground">
                                {order.pickup_address?.address_text ?? '—'}
                            </p>
                        </section>

                        <section className="space-y-1">
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Entrega
                            </p>
                            <p className="text-muted-foreground">
                                {order.delivery_address?.address_text ?? '—'}
                            </p>
                        </section>

                        <section className="space-y-2">
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Productos
                            </p>
                            {(order.items ?? []).length === 0 ? (
                                <p className="text-muted-foreground">
                                    Sin productos
                                </p>
                            ) : (
                                <ul className="space-y-2">
                                    {(order.items ?? []).map((item) => (
                                        <li
                                            key={item.id}
                                            className="flex justify-between gap-3"
                                        >
                                            <span className="min-w-0 text-navy">
                                                {item.line_label}
                                            </span>
                                            <span className="shrink-0 font-medium text-navy">
                                                {formatMoney(item.subtotal)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {order.notes ? (
                                <p className="text-xs text-muted-foreground">
                                    Nota del pedido: {order.notes}
                                </p>
                            ) : null}
                        </section>

                        {order.payment ? (
                            <section className="space-y-2 rounded-lg border border-border bg-muted/30 px-3 py-3">
                                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    Cobro
                                </p>
                                <DriverOrderPaymentInfo
                                    payment={order.payment}
                                    compact
                                />
                            </section>
                        ) : null}

                        <section className="space-y-2 rounded-lg border border-border px-3 py-3">
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Tu ganancia
                            </p>
                            {commission > 0 ? (
                                <>
                                    <div className="flex justify-between gap-3 text-muted-foreground">
                                        <span>Bruto</span>
                                        <span>
                                            {formatMoney(
                                                order.gross_earning ??
                                                    order.driver_earning,
                                            )}
                                        </span>
                                    </div>
                                    <div className="flex justify-between gap-3 text-muted-foreground">
                                        <span>ChisDrive</span>
                                        <span>
                                            {formatMoney(
                                                order.driver_commission ??
                                                    '0.00',
                                            )}
                                        </span>
                                    </div>
                                </>
                            ) : null}
                            <div className="flex justify-between gap-3 font-semibold text-navy">
                                <span>Neto</span>
                                <span>
                                    {formatMoney(order.driver_earning)}
                                </span>
                            </div>
                        </section>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
