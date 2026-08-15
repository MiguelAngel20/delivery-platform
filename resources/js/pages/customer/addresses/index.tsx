import { Head, useForm } from '@inertiajs/react';
import { FormField } from '@/components/forms/form-field';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { AddressPicker } from '@/components/maps/address-picker';
import { Button } from '@/components/ui/button';
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

export default function CustomerAddressesIndex({
    addresses,
    maxAddresses,
}: Props) {
    const form = useForm({
        label: '',
        address_text: '',
        formatted_address: '',
        reference: '',
        latitude: '',
        longitude: '',
        place_id: '',
        google_maps_url: '',
        is_default: addresses.length === 0,
    });

    const onAddressChange = (value: AddressValue) => {
        form.setData({
            ...form.data,
            address_text: value.address_text,
            formatted_address: value.formatted_address ?? '',
            reference: value.reference ?? '',
            latitude: String(value.latitude),
            longitude: String(value.longitude),
            place_id: value.place_id ?? '',
            google_maps_url: value.google_maps_url ?? '',
        });
    };

    return (
        <>
            <Head title="Direcciones" />
            <PageContainer className="gap-5 px-4 py-4 md:px-6">
                <PageHeader
                    title="Mis direcciones"
                    description={`Máximo ${maxAddresses} activas`}
                />

                <div className="space-y-3">
                    {addresses.map((address) => (
                        <div
                            key={address.id}
                            className="rounded-xl border border-border bg-surface p-4"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
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
                                    size="sm"
                                    onClick={() =>
                                        form.delete(destroy.url(address.id))
                                    }
                                >
                                    Eliminar
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>

                {addresses.length < maxAddresses ? (
                    <form
                        className="space-y-3 rounded-xl border border-border bg-surface p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(store.url(), { preserveScroll: true });
                        }}
                    >
                        <h2 className="font-semibold text-navy">
                            Nueva dirección
                        </h2>
                        <FormField label="Etiqueta" required error={form.errors.label}>
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
                            onChange={onAddressChange}
                        />
                        {form.errors.address_text || form.errors.latitude ? (
                            <p className="text-sm text-destructive">
                                {form.errors.address_text ?? form.errors.latitude}
                            </p>
                        ) : null}
                        <Button type="submit" disabled={form.processing}>
                            Guardar dirección
                        </Button>
                    </form>
                ) : null}
            </PageContainer>
        </>
    );
}
