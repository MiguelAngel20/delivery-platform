import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { OpeningHoursFields } from '@/apps/admin/businesses/opening-hours-fields';
import type {
    BusinessOpeningHour,
    EnumOption,
} from '@/apps/admin/businesses/types';
import { FormField } from '@/components/forms/form-field';
import { AddressPicker } from '@/components/maps/address-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { AddressValue } from '@/lib/maps/types';

export type BusinessBranchDetail = {
    id: number;
    name: string;
    phone: string | null;
    address_text: string;
    reference: string | null;
    latitude: string;
    longitude: string;
    google_maps_url: string | null;
    opening_hours: BusinessOpeningHour[];
    status_label: string;
    schedule_label?: string;
};

type BusinessBranchFormProps = {
    branch: BusinessBranchDetail;
    options: {
        weekdays: EnumOption[];
        default_opening_hours: BusinessOpeningHour[];
    };
    action: {
        url: string;
        method: 'post' | 'put' | 'patch';
    };
    submitLabel: string;
    cancelSlot?: React.ReactNode;
};

export function BusinessBranchForm({
    branch,
    options,
    action,
    submitLabel,
    cancelSlot,
}: BusinessBranchFormProps) {
    const [address, setAddress] = useState<Partial<AddressValue>>({
        address_text: branch.address_text ?? '',
        reference: branch.reference ?? '',
        latitude: branch.latitude ? Number(branch.latitude) : undefined,
        longitude: branch.longitude ? Number(branch.longitude) : undefined,
        google_maps_url: branch.google_maps_url ?? '',
    });

    return (
        <Form action={action.url} method={action.method} className="space-y-6">
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-4 md:grid-cols-2">
                        <FormField
                            label="Nombre"
                            htmlFor="branch_name"
                            required
                            error={errors.name}
                            className="md:col-span-2"
                        >
                            <Input
                                id="branch_name"
                                name="name"
                                required
                                defaultValue={branch.name}
                            />
                        </FormField>
                        <FormField
                            label="Teléfono"
                            htmlFor="branch_phone"
                            error={errors.phone}
                        >
                            <Input
                                id="branch_phone"
                                name="phone"
                                defaultValue={branch.phone ?? ''}
                            />
                        </FormField>
                        <FormField label="Estado">
                            <p className="text-sm text-muted-foreground">
                                {branch.status_label}
                            </p>
                        </FormField>
                        <div className="md:col-span-2 space-y-2">
                            <AddressPicker
                                value={address}
                                onChange={setAddress}
                                mapHeightClassName="h-[min(58vh,32rem)]"
                            />
                            <input
                                type="hidden"
                                name="address_text"
                                value={address.address_text ?? ''}
                            />
                            <input
                                type="hidden"
                                name="formatted_address"
                                value={address.formatted_address ?? ''}
                            />
                            <input
                                type="hidden"
                                name="reference"
                                value={address.reference ?? ''}
                            />
                            <input
                                type="hidden"
                                name="latitude"
                                value={address.latitude ?? ''}
                            />
                            <input
                                type="hidden"
                                name="longitude"
                                value={address.longitude ?? ''}
                            />
                            <input
                                type="hidden"
                                name="place_id"
                                value={address.place_id ?? ''}
                            />
                            <input
                                type="hidden"
                                name="google_maps_url"
                                value={address.google_maps_url ?? ''}
                            />
                            {errors.address_text || errors.latitude ? (
                                <p className="text-sm text-destructive">
                                    {errors.address_text ?? errors.latitude}
                                </p>
                            ) : null}
                        </div>
                        <OpeningHoursFields
                            weekdays={options.weekdays}
                            defaultHours={options.default_opening_hours}
                            value={branch.opening_hours}
                            errors={errors}
                            idPrefix={`branch-${branch.id}`}
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button type="submit" loading={processing}>
                            {submitLabel}
                        </Button>
                        {cancelSlot}
                    </div>
                </>
            )}
        </Form>
    );
}
