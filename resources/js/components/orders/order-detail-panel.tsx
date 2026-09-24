import { type ReactNode } from 'react';
import { OrderContactLinks } from '@/components/orders/order-contact-links';
import {
    OrderCopyButton,
    OrderCopyMapActions,
} from '@/components/orders/order-copy-map-actions';
import {
    formatOrderItemsPlainText,
    OrderItemsGrouped,
    type GroupedOrderItem,
} from '@/components/orders/order-items-grouped';
import { cn } from '@/lib/utils';

type PickupAddress = {
    address_text?: string | null;
    google_maps_url?: string | null;
} | null;

type OrderDetailPanelProps = {
    orderNumber: string;
    businessName: string;
    businessPhone?: string | null;
    items: GroupedOrderItem[];
    pickupAddress?: PickupAddress;
    notes?: string | null;
    footer?: ReactNode;
    className?: string;
};

function restaurantCopyText(
    businessName: string,
    businessPhone: string | null | undefined,
    pickupAddress: PickupAddress,
): string {
    return [
        businessName,
        businessPhone?.trim() ? `Tel: ${businessPhone.trim()}` : null,
        pickupAddress?.address_text?.trim()
            ? `Recogida: ${pickupAddress.address_text.trim()}`
            : null,
    ]
        .filter(Boolean)
        .join('\n');
}

export function OrderDetailPanel({
    orderNumber,
    businessName,
    businessPhone = null,
    items,
    pickupAddress = null,
    notes = null,
    footer,
    className,
}: OrderDetailPanelProps) {
    return (
        <section
            className={cn(
                'min-w-0 overflow-hidden rounded-xl border border-border bg-surface text-foreground shadow-sm',
                className,
            )}
        >
            <div className="flex min-w-0 items-start justify-between gap-2 border-b border-border px-4 py-4 md:gap-3 md:px-5">
                <div className="min-w-0 flex-1 space-y-1">
                    <h2 className="text-base font-semibold text-foreground">
                        Detalle
                    </h2>
                    <p className="text-sm font-bold break-words text-navy">
                        {businessName}
                    </p>
                    <div className="flex min-w-0 flex-wrap items-center gap-2">
                        <p className="text-sm break-all text-muted-foreground">
                            Tel: {businessPhone?.trim() || '—'}
                        </p>
                        <OrderContactLinks
                            phone={businessPhone}
                            whatsappText={`Hola, escribo por el pedido #${orderNumber}.`}
                        />
                    </div>
                </div>
                <OrderCopyMapActions
                    mapsUrl={pickupAddress?.google_maps_url}
                    mapsLabel="Abrir ubicación del negocio"
                    copyLabel="Negocio"
                    copyAriaLabel="Copiar información del negocio"
                    successMessage="Datos del negocio copiados"
                    getCopyText={() =>
                        restaurantCopyText(
                            businessName,
                            businessPhone,
                            pickupAddress,
                        )
                    }
                />
            </div>

            <div className="min-w-0 space-y-3 p-4 md:p-5">
                <div className="flex min-w-0 items-center justify-between gap-2">
                    <h3 className="min-w-0 flex-1 text-sm font-semibold break-all text-foreground">
                        Pedido #{orderNumber}
                    </h3>
                    <OrderCopyButton
                        label="Pedido"
                        ariaLabel="Copiar productos del pedido"
                        successMessage="Pedido copiado (sin precios)"
                        getCopyText={() => formatOrderItemsPlainText(items)}
                    />
                </div>

                <OrderItemsGrouped items={items} showCopyButton={false} />

                {pickupAddress?.address_text?.trim() ? (
                    <p className="text-sm break-words text-muted-foreground">
                        Recogida: {pickupAddress.address_text.trim()}
                    </p>
                ) : null}

                {notes?.trim() ? (
                    <p className="text-sm break-words text-foreground">
                        {notes.trim()}
                    </p>
                ) : null}

                {footer}
            </div>
        </section>
    );
}
