import { type ReactNode } from 'react';
import { OrderContactLinks } from '@/components/orders/order-contact-links';
import { OrderCopyMapActions } from '@/components/orders/order-copy-map-actions';
import { cn } from '@/lib/utils';

export type OrderCustomerInfo = {
    name?: string | null;
    phone?: string | null;
};

export type OrderDeliveryAddress = {
    address_text: string;
    reference?: string | null;
    google_maps_url?: string | null;
} | null;

type OrderCustomerPanelProps = {
    customer: OrderCustomerInfo;
    deliveryAddress?: OrderDeliveryAddress;
    orderNumber?: string | null;
    title?: string;
    footer?: ReactNode;
    className?: string;
};

export function formatCustomerPlainText(
    customer: OrderCustomerInfo,
    deliveryAddress?: OrderDeliveryAddress,
): string {
    const lines: string[] = [
        `Cliente: ${customer.name?.trim() || '—'}`,
        `Tel: ${customer.phone?.trim() || '—'}`,
    ];

    if (deliveryAddress?.address_text?.trim()) {
        lines.push(`Dirección: ${deliveryAddress.address_text.trim()}`);
    }

    if (deliveryAddress?.reference?.trim()) {
        lines.push(`Ref: ${deliveryAddress.reference.trim()}`);
    }

    return lines.join('\n');
}

export function OrderCustomerPanel({
    customer,
    deliveryAddress = null,
    orderNumber = null,
    title = 'Cliente',
    footer,
    className,
}: OrderCustomerPanelProps) {
    const whatsappText = orderNumber
        ? `Hola, escribo por el pedido #${orderNumber}.`
        : undefined;

    return (
        <div className={cn('min-w-0 space-y-3', className)}>
            <div className="flex min-w-0 items-start justify-between gap-2">
                <h2 className="min-w-0 font-semibold text-foreground">{title}</h2>
                <OrderCopyMapActions
                    mapsUrl={deliveryAddress?.google_maps_url}
                    mapsLabel="Abrir ubicación del cliente"
                    copyAriaLabel="Copiar datos del cliente"
                    successMessage="Datos del cliente copiados"
                    getCopyText={() =>
                        formatCustomerPlainText(customer, deliveryAddress)
                    }
                />
            </div>

            <div className="space-y-1 text-sm">
                <p className="font-medium break-words text-foreground">
                    {customer.name?.trim() || 'Cliente'}
                </p>
                <div className="flex min-w-0 flex-wrap items-center gap-2">
                    <p className="break-all text-muted-foreground">
                        Tel: {customer.phone?.trim() || '—'}
                    </p>
                    <OrderContactLinks
                        phone={customer.phone}
                        whatsappText={whatsappText}
                    />
                </div>
            </div>

            <div className="min-w-0 space-y-1">
                <h3 className="text-sm font-semibold text-foreground">
                    Dirección de entrega
                </h3>
                <p className="text-sm break-words text-muted-foreground">
                    {deliveryAddress?.address_text?.trim() || '—'}
                </p>
                {deliveryAddress?.reference?.trim() ? (
                    <p className="text-sm break-words text-muted-foreground">
                        Ref: {deliveryAddress.reference.trim()}
                    </p>
                ) : null}
            </div>

            {footer}
        </div>
    );
}
