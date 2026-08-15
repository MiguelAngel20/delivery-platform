import { useState } from 'react';
import { AddressPicker } from '@/components/maps/address-picker';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useDeliveryLocation } from '@/apps/storefront/hooks/use-delivery-location';
import type { AddressValue } from '@/lib/maps/types';
import { router } from '@inertiajs/react';
import restaurants from '@/routes/restaurants';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function DeliveryLocationDialog({ open, onOpenChange }: Props) {
    const { location, setLocation } = useDeliveryLocation();
    const [draft, setDraft] = useState<Partial<AddressValue>>({
        address_text: location.detail,
        latitude: location.latitude ?? undefined,
        longitude: location.longitude ?? undefined,
        formatted_address: location.formatted_address,
        place_id: location.place_id,
        reference: location.reference,
    });

    const save = () => {
        if (draft.latitude == null || draft.longitude == null || !draft.address_text) {
            return;
        }

        setLocation({
            label: 'Entrega',
            detail: draft.address_text,
            latitude: draft.latitude,
            longitude: draft.longitude,
            formatted_address: draft.formatted_address ?? null,
            place_id: draft.place_id ?? null,
            reference: draft.reference ?? null,
        });

        onOpenChange(false);

        router.get(
            restaurants.index.url({
                query: {
                    lat: draft.latitude,
                    lng: draft.longitude,
                },
            }),
            {},
            { preserveState: false },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>¿Dónde entregamos?</DialogTitle>
                </DialogHeader>
                <AddressPicker
                    value={draft}
                    showCurrentLocation
                    onChange={setDraft}
                />
                <Button
                    type="button"
                    className="min-h-12 w-full"
                    onClick={save}
                    disabled={draft.latitude == null || draft.longitude == null}
                >
                    Usar esta ubicación
                </Button>
            </DialogContent>
        </Dialog>
    );
}
