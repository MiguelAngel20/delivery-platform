import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { CoverageUnavailableBanner } from '@/apps/storefront/components/coverage-unavailable-banner';
import {
    COVERAGE_UNAVAILABLE_MESSAGE,
    checkDeliveryCoverage,
} from '@/apps/storefront/lib/check-delivery-coverage';
import { FormField } from '@/components/forms/form-field';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { AddressPicker } from '@/components/maps/address-picker';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import type { AddressValue } from '@/lib/maps/types';
import { destroy, store } from '@/routes/customer/addresses';

type Address = {
    id: number;
    label: string;
    address_text: string;
    reference?: string | null;
    latitude: string;
    longitude: string;
    is_default: boolean;
};

type Props = {
    addresses: Address[];
    maxAddresses: number;
};

const emptyForm = {
    label: '',
    address_text: '',
    formatted_address: '',
    reference: '',
    latitude: '',
    longitude: '',
    place_id: '',
    google_maps_url: '',
    is_default: false,
};

export default function CustomerAddressesIndex({
    addresses,
    maxAddresses,
}: Props) {
    const [open, setOpen] = useState(false);
    const [coverageError, setCoverageError] = useState<string | null>(null);
    const [checkingCoverage, setCheckingCoverage] = useState(false);
    const canAdd = addresses.length < maxAddresses;
    const form = useForm({
        ...emptyForm,
        is_default: addresses.length === 0,
    });

    const openCreate = () => {
        form.clearErrors();
        setCoverageError(null);
        form.setData({
            ...emptyForm,
            is_default: addresses.length === 0,
        });
        setOpen(true);
    };

    const onAddressChange = (value: AddressValue) => {
        form.setData((data) => ({
            ...data,
            address_text: value.address_text,
            formatted_address: value.formatted_address ?? '',
            reference: value.reference ?? data.reference,
            latitude: String(value.latitude),
            longitude: String(value.longitude),
            place_id: value.place_id ?? '',
            google_maps_url: value.google_maps_url ?? '',
        }));
        form.clearErrors('latitude', 'address_text');
        setCoverageError(null);

        void (async () => {
            setCheckingCoverage(true);

            try {
                const result = await checkDeliveryCoverage(
                    value.latitude,
                    value.longitude,
                );

                if (!result.covered) {
                    setCoverageError(
                        result.message ?? COVERAGE_UNAVAILABLE_MESSAGE,
                    );
                }
            } catch {
                // Server still validates.
            } finally {
                setCheckingCoverage(false);
            }
        })();
    };

    return (
        <>
            <Head title="Direcciones" />
            <PageContainer className="gap-5 px-4 py-4 md:px-6">
                <PageHeader
                    title="Mis direcciones"
                    description={`Máximo ${maxAddresses} activas`}
                    actions={
                        canAdd ? (
                            <Button
                                type="button"
                                className="gap-1.5"
                                onClick={openCreate}
                            >
                                <Plus className="size-4" aria-hidden />
                                Agregar dirección
                            </Button>
                        ) : null
                    }
                />

                <div className="space-y-3">
                    {addresses.length === 0 ? (
                        <p className="rounded-xl border border-dashed border-border bg-surface px-4 py-8 text-center text-sm text-muted-foreground">
                            Aún no tienes direcciones guardadas.
                        </p>
                    ) : (
                        addresses.map((address) => (
                            <div
                                key={address.id}
                                className="rounded-xl border border-border bg-surface p-4"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="font-semibold text-navy">
                                            {address.label}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {address.address_text}
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-9 shrink-0 text-muted-foreground hover:text-destructive"
                                        aria-label={`Eliminar ${address.label}`}
                                        onClick={() =>
                                            form.delete(destroy.url(address.id))
                                        }
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            </div>
                        ))
                    )}
                </div>
            </PageContainer>

            <Dialog
                open={open}
                onOpenChange={(next) => {
                    setOpen(next);
                    if (!next) {
                        form.clearErrors();
                        setCoverageError(null);
                    }
                }}
            >
                <DialogContent className="flex max-h-[95dvh] flex-col gap-3 overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Nueva dirección</DialogTitle>
                    </DialogHeader>
                    <form
                        className="space-y-3"
                        onSubmit={(event) => {
                            event.preventDefault();

                            if (coverageError) {
                                return;
                            }

                            form.post(store.url(), {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setOpen(false);
                                    form.reset();
                                    form.setData(
                                        'is_default',
                                        addresses.length === 0,
                                    );
                                },
                            });
                        }}
                    >
                        <FormField
                            label="Etiqueta"
                            required
                            error={form.errors.label}
                        >
                            <Input
                                value={form.data.label}
                                onChange={(event) =>
                                    form.setData('label', event.target.value)
                                }
                                placeholder="Casa"
                            />
                        </FormField>
                        <AddressPicker
                            value={{
                                address_text: form.data.address_text,
                                formatted_address: form.data.formatted_address,
                                reference: form.data.reference,
                                latitude: form.data.latitude
                                    ? Number(form.data.latitude)
                                    : undefined,
                                longitude: form.data.longitude
                                    ? Number(form.data.longitude)
                                    : undefined,
                                place_id: form.data.place_id,
                            }}
                            showCurrentLocation
                            mapHeightClassName="h-[min(45vh,20rem)] sm:h-80"
                            onChange={onAddressChange}
                        />
                        {coverageError || form.errors.latitude ? (
                            <CoverageUnavailableBanner
                                message={
                                    coverageError ?? form.errors.latitude
                                }
                            />
                        ) : null}
                        {form.errors.address_text && !coverageError ? (
                            <p className="text-sm text-destructive">
                                {form.errors.address_text}
                            </p>
                        ) : null}
                        {checkingCoverage ? (
                            <p className="text-xs text-muted-foreground">
                                Verificando cobertura…
                            </p>
                        ) : null}
                        <Button
                            type="submit"
                            className="min-h-12 w-full"
                            disabled={
                                form.processing ||
                                Boolean(coverageError) ||
                                checkingCoverage
                            }
                        >
                            Guardar dirección
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
