import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { StatusBadge } from '@/components/data-display/status-badge';
import { FormField } from '@/components/forms/form-field';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { AddressPicker } from '@/components/maps/address-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { AddressValue } from '@/lib/maps/types';
import {
    activate,
    deactivate,
    store,
    update,
} from '@/routes/admin/coverage';

type Zone = {
    id: number;
    name: string;
    scope_type: string;
    scope_type_label: string;
    scope_id: number | null;
    zone_type: string;
    zone_type_label: string;
    center_latitude: string | null;
    center_longitude: string | null;
    radius_meters: number | null;
    is_active: boolean;
};

type BranchOption = {
    id: number;
    name: string;
    latitude: string;
    longitude: string;
};

type ServiceFeeTariff = {
    base_meters: number;
    base_fee: string;
    step_meters: number;
    step_fee: string;
};

type Props = {
    zones: {
        data: Zone[];
    };
    branches: BranchOption[];
    serviceFeeTariff: ServiceFeeTariff;
    options: {
        scope_types: Array<{ value: string; label: string }>;
        zone_types: Array<{ value: string; label: string }>;
        radius_presets_meters: number[];
    };
    maps: {
        browser_api_key?: string;
        default_center: {
            latitude: number;
            longitude: number;
            zoom: number;
        };
        default_place_label?: string;
    };
};

function formatMoney(value: number): string {
    return `$${value.toFixed(2)}`;
}

function previewFee(
    distanceMeters: number,
    baseMeters: number,
    baseFee: number,
    stepMeters: number,
    stepFee: number,
): number {
    if (distanceMeters <= baseMeters) {
        return baseFee;
    }

    const steps = Math.ceil((distanceMeters - baseMeters) / stepMeters);

    return baseFee + steps * stepFee;
}

export default function AdminCoverageIndex({
    zones,
    branches,
    serviceFeeTariff,
    options,
    maps,
}: Props) {
    const [editing, setEditing] = useState<Zone | null>(null);
    const form = useForm({
        name: '',
        scope_type: 'platform',
        scope_id: '' as string | number,
        zone_type: 'radius',
        center_latitude: String(maps.default_center.latitude),
        center_longitude: String(maps.default_center.longitude),
        radius_meters: 5000,
        is_active: true,
    });

    const tariffForm = useForm({
        base_meters: serviceFeeTariff.base_meters,
        base_fee: serviceFeeTariff.base_fee,
        step_meters: serviceFeeTariff.step_meters,
        step_fee: serviceFeeTariff.step_fee,
    });

    const preview = useMemo(() => {
        const baseMeters = Number(tariffForm.data.base_meters) || 1000;
        const baseFee = Number(tariffForm.data.base_fee) || 0;
        const stepMeters = Number(tariffForm.data.step_meters) || 500;
        const stepFee = Number(tariffForm.data.step_fee) || 0;

        return [
            { label: `0–${baseMeters} m`, fee: previewFee(baseMeters, baseMeters, baseFee, stepMeters, stepFee) },
            {
                label: `${baseMeters + 1}–${baseMeters + stepMeters} m`,
                fee: previewFee(baseMeters + 1, baseMeters, baseFee, stepMeters, stepFee),
            },
            {
                label: `${baseMeters + stepMeters + 1}–${baseMeters + stepMeters * 2} m`,
                fee: previewFee(
                    baseMeters + stepMeters + 1,
                    baseMeters,
                    baseFee,
                    stepMeters,
                    stepFee,
                ),
            },
        ];
    }, [tariffForm.data]);

    const applyZone = (zone?: Zone | null) => {
        setEditing(zone ?? null);
        form.setData({
            name: zone?.name ?? '',
            scope_type: zone?.scope_type ?? 'platform',
            scope_id: zone?.scope_id ?? '',
            zone_type: 'radius',
            center_latitude:
                zone?.center_latitude ?? String(maps.default_center.latitude),
            center_longitude:
                zone?.center_longitude ?? String(maps.default_center.longitude),
            radius_meters: zone?.radius_meters ?? 5000,
            is_active: zone?.is_active ?? true,
        });
    };

    const submitZone = (event: React.FormEvent) => {
        event.preventDefault();

        if (editing) {
            form.put(update.url(editing.id), { preserveScroll: true });
        } else {
            form.post(store.url(), {
                preserveScroll: true,
                onSuccess: () => applyZone(null),
            });
        }
    };

    const submitTariff = (event: React.FormEvent) => {
        event.preventDefault();
        tariffForm.put('/admin/coverage/tariff', { preserveScroll: true });
    };

    return (
        <>
            <Head title="Cobertura" />
            <PageContainer className="gap-5 px-4 py-4 md:px-6">
                <PageHeader
                    title="Cobertura y tarifa"
                    description="La cobertura define dónde entregamos. La tarifa define el servicio según kilómetros."
                />

                <ContentCard
                    title="Tarifa de servicio (por distancia)"
                    description="Precio según distancia sucursal → entrega. Editable cuando quieras ajustar precios."
                >
                    <form className="space-y-3" onSubmit={submitTariff}>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <FormField
                                label="Metros incluidos en el precio base"
                                required
                                error={tariffForm.errors.base_meters}
                            >
                                <Input
                                    type="number"
                                    min={1}
                                    value={tariffForm.data.base_meters}
                                    onChange={(event) =>
                                        tariffForm.setData(
                                            'base_meters',
                                            Number(event.target.value),
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                label="Precio base ($)"
                                required
                                error={tariffForm.errors.base_fee}
                            >
                                <Input
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={tariffForm.data.base_fee}
                                    onChange={(event) =>
                                        tariffForm.setData(
                                            'base_fee',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                label="Metros por tramo extra"
                                required
                                error={tariffForm.errors.step_meters}
                            >
                                <Input
                                    type="number"
                                    min={1}
                                    value={tariffForm.data.step_meters}
                                    onChange={(event) =>
                                        tariffForm.setData(
                                            'step_meters',
                                            Number(event.target.value),
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                label="Incremento por tramo ($)"
                                required
                                error={tariffForm.errors.step_fee}
                            >
                                <Input
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={tariffForm.data.step_fee}
                                    onChange={(event) =>
                                        tariffForm.setData(
                                            'step_fee',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        </div>

                        <div className="rounded-xl border border-border bg-muted/40 p-3 text-sm">
                            <p className="mb-2 font-medium text-navy">
                                Vista previa
                            </p>
                            <ul className="space-y-1 text-muted-foreground">
                                {preview.map((row) => (
                                    <li
                                        key={row.label}
                                        className="flex justify-between gap-3"
                                    >
                                        <span>{row.label}</span>
                                        <span className="font-medium text-navy">
                                            {formatMoney(row.fee)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <Button type="submit" disabled={tariffForm.processing}>
                            Guardar tarifa
                        </Button>
                    </form>
                </ContentCard>

                <div className="grid gap-5 lg:grid-cols-2">
                    <ContentCard
                        title={editing ? 'Editar zona' : 'Nueva zona de cobertura'}
                        description="Solo delimita dónde sí entregamos (radio). No define el precio."
                    >
                        <form className="space-y-3" onSubmit={submitZone}>
                            <FormField label="Nombre" required error={form.errors.name}>
                                <Input
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    placeholder="Ej. Comitán"
                                />
                            </FormField>
                            <FormField label="Alcance" required>
                                <select
                                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                    value={form.data.scope_type}
                                    onChange={(event) =>
                                        form.setData('scope_type', event.target.value)
                                    }
                                >
                                    {options.scope_types.map((option) => (
                                        <option key={option.value} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </FormField>
                            {form.data.scope_type === 'business_branch' ? (
                                <FormField label="Sucursal" required error={form.errors.scope_id}>
                                    <select
                                        className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                        value={String(form.data.scope_id ?? '')}
                                        onChange={(event) => {
                                            const branch = branches.find(
                                                (item) =>
                                                    String(item.id) === event.target.value,
                                            );
                                            form.setData({
                                                ...form.data,
                                                scope_id: event.target.value,
                                                center_latitude:
                                                    branch?.latitude ??
                                                    form.data.center_latitude,
                                                center_longitude:
                                                    branch?.longitude ??
                                                    form.data.center_longitude,
                                            });
                                        }}
                                    >
                                        <option value="">Selecciona</option>
                                        {branches.map((branch) => (
                                            <option key={branch.id} value={branch.id}>
                                                {branch.name}
                                            </option>
                                        ))}
                                    </select>
                                </FormField>
                            ) : null}
                            <FormField label="Radio">
                                <div className="flex flex-wrap gap-2">
                                    {options.radius_presets_meters.map((meters) => (
                                        <Button
                                            key={meters}
                                            type="button"
                                            size="sm"
                                            variant={
                                                form.data.radius_meters === meters
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            onClick={() =>
                                                form.setData('radius_meters', meters)
                                            }
                                        >
                                            {meters / 1000} km
                                        </Button>
                                    ))}
                                </div>
                                <Input
                                    className="mt-2"
                                    type="number"
                                    min={100}
                                    value={form.data.radius_meters}
                                    onChange={(event) =>
                                        form.setData(
                                            'radius_meters',
                                            Number(event.target.value),
                                        )
                                    }
                                />
                            </FormField>
                            <AddressPicker
                                showReference={false}
                                radiusMeters={form.data.radius_meters}
                                value={{
                                    address_text: form.data.name || 'Centro de zona',
                                    latitude: Number(form.data.center_latitude),
                                    longitude: Number(form.data.center_longitude),
                                }}
                                onChange={(value: AddressValue) => {
                                    form.setData({
                                        ...form.data,
                                        center_latitude: String(value.latitude),
                                        center_longitude: String(value.longitude),
                                    });
                                }}
                            />
                            <div className="flex gap-2">
                                <Button type="submit" disabled={form.processing}>
                                    {editing ? 'Actualizar' : 'Crear zona'}
                                </Button>
                                {editing ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => applyZone(null)}
                                    >
                                        Cancelar
                                    </Button>
                                ) : null}
                            </div>
                        </form>
                    </ContentCard>

                    <ContentCard title="Zonas activas">
                        <div className="space-y-3">
                            {zones.data.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Sin zonas. Sin zonas de plataforma activas se
                                    permite entregar en cualquier lado (modo
                                    seguro de despliegue).
                                </p>
                            ) : (
                                zones.data.map((zone) => (
                                    <div
                                        key={zone.id}
                                        className="rounded-xl border border-border p-4"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="font-semibold text-navy">
                                                    {zone.name}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {zone.scope_type_label}
                                                    {zone.radius_meters
                                                        ? ` · ${(zone.radius_meters / 1000).toFixed(1)} km`
                                                        : ''}
                                                </p>
                                            </div>
                                            <StatusBadge
                                                tone={zone.is_active ? 'success' : 'neutral'}
                                            >
                                                {zone.is_active ? 'Activa' : 'Inactiva'}
                                            </StatusBadge>
                                        </div>
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() => applyZone(zone)}
                                            >
                                                Editar
                                            </Button>
                                            {zone.is_active ? (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.post(deactivate.url(zone.id))
                                                    }
                                                >
                                                    Desactivar
                                                </Button>
                                            ) : (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.post(activate.url(zone.id))
                                                    }
                                                >
                                                    Activar
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </ContentCard>
                </div>
            </PageContainer>
        </>
    );
}
