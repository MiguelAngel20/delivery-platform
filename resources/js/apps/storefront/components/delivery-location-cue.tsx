import { usePage } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import { useState } from 'react';
import { DeliveryLocationDialog } from '@/apps/storefront/components/delivery-location-dialog';
import { useDeliveryLocation } from '@/apps/storefront/hooks/use-delivery-location';
import { cn } from '@/lib/utils';
import type { Auth } from '@/types';

type DeliveryLocationCueProps = {
    className?: string;
};

type MapsSharedProps = {
    default_place_label?: string;
};

export function DeliveryLocationCue({ className }: DeliveryLocationCueProps) {
    const page = usePage();
    const { auth, maps } = page.props as {
        auth: Auth;
        maps?: MapsSharedProps;
    };
    const { location, hasCoordinates } = useDeliveryLocation();
    const [open, setOpen] = useState(false);

    const authenticated = auth.user?.role === 'customer';
    const defaultPlace =
        maps?.default_place_label ?? 'Comitán de Domínguez, Chiapas';

    const prefix = authenticated ? 'Entregar en:' : 'Lugar de entrega:';
    const place = hasCoordinates
        ? location.detail || location.formatted_address || location.label
        : authenticated
          ? location.label
          : defaultPlace;

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className={cn(
                    'flex w-full min-w-0 items-center gap-2 rounded-lg px-1 py-1 text-left transition-colors hover:bg-primary/5',
                    className,
                )}
                aria-label={
                    authenticated
                        ? 'Cambiar lugar de entrega'
                        : 'Ver lugar de entrega'
                }
            >
                <MapPin className="size-4 shrink-0 text-primary" aria-hidden />
                <span className="min-w-0 text-sm">
                    <span className="text-primary">{prefix}</span>{' '}
                    <span className="font-medium text-navy">{place}</span>
                </span>
            </button>

            <DeliveryLocationDialog open={open} onOpenChange={setOpen} />
        </>
    );
}
