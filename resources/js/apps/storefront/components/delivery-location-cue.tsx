import { usePage } from '@inertiajs/react';
import { ChevronDown, MapPin } from 'lucide-react';
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

    const place = hasCoordinates
        ? location.detail || location.formatted_address || location.label
        : authenticated
          ? location.label
          : defaultPlace;

    const compactPlace = place.replace(/,?\s*Chiapas$/i, '').trim() || place;

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className={cn(
                    'flex min-w-0 items-center gap-1 rounded-md py-0.5 text-left transition-colors hover:bg-primary/5',
                    className,
                )}
                aria-label={
                    authenticated
                        ? `Cambiar lugar de entrega: ${place}`
                        : `Lugar de entrega: ${place}`
                }
            >
                <MapPin className="size-3 shrink-0 text-primary" aria-hidden />
                <span className="min-w-0 truncate text-[11px] font-medium leading-tight text-navy md:text-sm">
                    {compactPlace}
                </span>
                <ChevronDown
                    className="size-3 shrink-0 text-muted-foreground"
                    aria-hidden
                />
            </button>

            <DeliveryLocationDialog open={open} onOpenChange={setOpen} />
        </>
    );
}
