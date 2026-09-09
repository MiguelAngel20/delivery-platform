import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { AddressCard } from '@/apps/storefront/components/address-card';
import {
    type DeliveryLocation,
    useDeliveryLocation,
} from '@/apps/storefront/hooks/use-delivery-location';
import { AddressPicker } from '@/components/maps/address-picker';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { AddressValue } from '@/lib/maps/types';
import { cn } from '@/lib/utils';
import addresses from '@/routes/customer/addresses';
import restaurants from '@/routes/restaurants';
import type { Auth } from '@/types';

type SavedAddress = {
    id: string;
    label: string;
    line: string;
    address_text: string;
    reference?: string | null;
    latitude: string;
    longitude: string;
    formatted_address?: string | null;
    place_id?: string | null;
    isDefault: boolean;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

type Mode = 'saved' | 'search';

function applyAndBrowse(
    next: DeliveryLocation,
    setLocation: (location: DeliveryLocation) => void,
    onOpenChange: (open: boolean) => void,
): void {
    if (next.latitude == null || next.longitude == null) {
        return;
    }

    setLocation(next);
    onOpenChange(false);

    // Defer navigation so the dialog/maps can unmount cleanly first.
    window.setTimeout(() => {
        router.get(
            restaurants.index.url({
                query: {
                    lat: next.latitude,
                    lng: next.longitude,
                },
            }),
            {},
            { preserveState: false },
        );
    }, 0);
}

export function DeliveryLocationDialog({ open, onOpenChange }: Props) {
    const page = usePage();
    const { auth, customerAddresses: savedAddresses = [] } = page.props as {
        auth: Auth;
        customerAddresses?: SavedAddress[];
    };
    const { location, setLocation } = useDeliveryLocation();
    const authenticated = auth.user?.role === 'customer';
    const hasSaved = savedAddresses.length > 0;

    const [mode, setMode] = useState<Mode>(hasSaved ? 'saved' : 'search');
    const [draft, setDraft] = useState<Partial<AddressValue>>({
        address_text: location.detail,
        latitude: location.latitude ?? undefined,
        longitude: location.longitude ?? undefined,
        formatted_address: location.formatted_address,
        place_id: location.place_id,
        reference: location.reference,
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        setMode(hasSaved ? 'saved' : 'search');
        setDraft({
            address_text:
                location.address_id != null
                    ? ''
                    : (location.detail ?? ''),
            latitude: location.latitude ?? undefined,
            longitude: location.longitude ?? undefined,
            formatted_address: location.formatted_address,
            place_id: location.place_id,
            reference: location.reference,
        });
    }, [open, hasSaved, location]);

    const selectSaved = (address: SavedAddress) => {
        applyAndBrowse(
            {
                label: address.label,
                detail: address.address_text,
                latitude: Number(address.latitude),
                longitude: Number(address.longitude),
                formatted_address: address.formatted_address ?? null,
                place_id: address.place_id ?? null,
                reference: address.reference ?? null,
                address_id: address.id,
            },
            setLocation,
            onOpenChange,
        );
    };

    const saveSearch = () => {
        if (
            draft.latitude == null ||
            draft.longitude == null ||
            !draft.address_text
        ) {
            return;
        }

        const shortLabel =
            draft.address_text.split(',')[0]?.trim() || 'Entrega';

        applyAndBrowse(
            {
                label: shortLabel,
                detail: draft.address_text,
                latitude: draft.latitude,
                longitude: draft.longitude,
                formatted_address: draft.formatted_address ?? null,
                place_id: draft.place_id ?? null,
                reference: draft.reference ?? null,
                address_id: null,
            },
            setLocation,
            onOpenChange,
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[95dvh] flex-col gap-3 overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>¿Dónde entregamos?</DialogTitle>
                </DialogHeader>

                {hasSaved ? (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant={mode === 'saved' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setMode('saved')}
                        >
                            Mis direcciones
                        </Button>
                        <Button
                            type="button"
                            variant={mode === 'search' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setMode('search')}
                        >
                            Otra ubicación
                        </Button>
                    </div>
                ) : null}

                {mode === 'saved' && hasSaved ? (
                    <div className="space-y-2">
                        {savedAddresses.map((address) => (
                            <AddressCard
                                key={address.id}
                                address={address}
                                selected={location.address_id === address.id}
                                onSelect={() => selectSaved(address)}
                            />
                        ))}
                        {authenticated ? (
                            <p className="pt-1 text-center text-xs text-muted-foreground">
                                <Link
                                    href={addresses.index()}
                                    className="font-medium text-primary underline-offset-2 hover:underline"
                                >
                                    Administrar direcciones
                                </Link>
                            </p>
                        ) : null}
                    </div>
                ) : (
                    <>
                        <AddressPicker
                            value={draft}
                            showCurrentLocation
                            mapHeightClassName="h-[min(45vh,20rem)] sm:h-80"
                            onChange={setDraft}
                        />
                        <Button
                            type="button"
                            className={cn('min-h-12 w-full')}
                            onClick={saveSearch}
                            disabled={
                                draft.latitude == null ||
                                draft.longitude == null
                            }
                        >
                            Usar esta ubicación
                        </Button>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
