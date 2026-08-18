import { Form, router } from '@inertiajs/react';
import { useState } from 'react';
import { OpeningHoursFields } from '@/apps/admin/businesses/opening-hours-fields';
import {
    branchStatusTone
} from '@/apps/admin/businesses/types';
import type {BusinessBranchItem, BusinessOpeningHour, EnumOption} from '@/apps/admin/businesses/types';
import { StatusBadge } from '@/components/data-display/status-badge';
import { ConfirmDialog, Modal } from '@/components/dialogs/modal';
import { EmptyState } from '@/components/feedback/empty-state';
import { FormField } from '@/components/forms/form-field';
import { AddressPicker } from '@/components/maps/address-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { AddressValue } from '@/lib/maps/types';
import {
    activate,
    deactivate,
    store,
    update,
} from '@/routes/admin/businesses/branches';

type BranchFormFieldsProps = {
    branch?: BusinessBranchItem;
    weekdays: EnumOption[];
    defaultOpeningHours: BusinessOpeningHour[];
    errors: Record<string, string>;
};

function BranchFormFields({
    branch,
    weekdays,
    defaultOpeningHours,
    errors,
}: BranchFormFieldsProps) {
    const [address, setAddress] = useState<Partial<AddressValue>>({
        address_text: branch?.address_text ?? '',
        reference: branch?.reference ?? '',
        latitude: branch?.latitude ? Number(branch.latitude) : undefined,
        longitude: branch?.longitude ? Number(branch.longitude) : undefined,
        google_maps_url: branch?.google_maps_url ?? '',
    });

    return (
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
                    defaultValue={branch?.name ?? ''}
                />
            </FormField>
            <FormField label="Teléfono" htmlFor="branch_phone" error={errors.phone}>
                <Input
                    id="branch_phone"
                    name="phone"
                    defaultValue={branch?.phone ?? ''}
                />
            </FormField>
            <FormField
                label="Estado"
                htmlFor="branch_status"
                required
                error={errors.status}
            >
                <select
                    id="branch_status"
                    name="status"
                    required
                    defaultValue={branch?.status ?? 'active'}
                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option value="active">Activa</option>
                    <option value="inactive">Inactiva</option>
                    <option value="suspended">Suspendida</option>
                </select>
            </FormField>
            <div className="md:col-span-2 space-y-2">
                <AddressPicker
                    value={address}
                    onChange={setAddress}
                    mapHeightClassName="h-[min(58vh,32rem)]"
                />
                <input type="hidden" name="address_text" value={address.address_text ?? ''} />
                <input type="hidden" name="formatted_address" value={address.formatted_address ?? ''} />
                <input type="hidden" name="reference" value={address.reference ?? ''} />
                <input type="hidden" name="latitude" value={address.latitude ?? ''} />
                <input type="hidden" name="longitude" value={address.longitude ?? ''} />
                <input type="hidden" name="place_id" value={address.place_id ?? ''} />
                <input type="hidden" name="google_maps_url" value={address.google_maps_url ?? ''} />
                {errors.address_text || errors.latitude ? (
                    <p className="text-sm text-destructive">
                        {errors.address_text ?? errors.latitude}
                    </p>
                ) : null}
            </div>
            <OpeningHoursFields
                weekdays={weekdays}
                defaultHours={defaultOpeningHours}
                value={branch?.opening_hours}
                errors={errors}
                idPrefix={branch ? `branch-${branch.id}` : 'branch-create'}
            />
        </div>
    );
}

type BranchListProps = {
    businessId: number;
    branches: BusinessBranchItem[];
    weekdays: EnumOption[];
    defaultOpeningHours: BusinessOpeningHour[];
    canCreateBranch?: boolean;
    branchesUsed?: number;
    maxBranches?: number;
};

export function BranchList({
    businessId,
    branches,
    weekdays,
    defaultOpeningHours,
    canCreateBranch = true,
    branchesUsed,
    maxBranches,
}: BranchListProps) {
    const [createOpen, setCreateOpen] = useState(false);
    const [editing, setEditing] = useState<BusinessBranchItem | null>(null);
    const [pendingDeactivate, setPendingDeactivate] =
        useState<BusinessBranchItem | null>(null);
    const [actionLoading, setActionLoading] = useState(false);

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                {branchesUsed !== undefined && maxBranches !== undefined ? (
                    <p className="text-sm text-muted-foreground">
                        Sucursales: {branchesUsed} / {maxBranches}
                    </p>
                ) : (
                    <span />
                )}
                <Button
                    type="button"
                    disabled={!canCreateBranch}
                    onClick={() => setCreateOpen(true)}
                >
                    + Nueva sucursal
                </Button>
            </div>
            {!canCreateBranch ? (
                <p className="text-sm text-muted-foreground">
                    La empresa alcanzó el límite de sucursales contratado.
                    Amplía el límite antes de crear otra sucursal.
                </p>
            ) : null}

            {branches.length === 0 ? (
                <EmptyState
                    title="Sin sucursales"
                    description="Crea la primera sucursal de esta empresa."
                />
            ) : (
                <div className="overflow-x-auto rounded-lg border border-[#E2E8F0]">
                    <table className="min-w-full text-sm">
                        <thead className="bg-[#F8FAFC] text-left text-[#64748B]">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Sucursal
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Dirección
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Teléfono
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Horario
                                </th>
                                <th className="px-4 py-3 font-medium">Estado</th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {branches.map((branch) => (
                                <tr
                                    key={branch.id}
                                    className="border-t border-[#E2E8F0]"
                                >
                                    <td className="px-4 py-3 font-medium text-navy">
                                        {branch.name}
                                    </td>
                                    <td className="px-4 py-3 text-[#475569]">
                                        {branch.address_text}
                                    </td>
                                    <td className="px-4 py-3 text-[#475569]">
                                        {branch.phone ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-[#475569]">
                                        {branch.schedule_label ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <StatusBadge
                                            tone={branchStatusTone(
                                                branch.status,
                                            )}
                                        >
                                            {branch.status_label}
                                        </StatusBadge>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap justify-end gap-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    setEditing(branch)
                                                }
                                            >
                                                Editar
                                            </Button>
                                            {branch.status === 'active' ? (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        setPendingDeactivate(
                                                            branch,
                                                        )
                                                    }
                                                >
                                                    Desactivar
                                                </Button>
                                            ) : (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    loading={actionLoading}
                                                    onClick={() => {
                                                        setActionLoading(true);
                                                        router.post(
                                                            activate.url({
                                                                business:
                                                                    businessId,
                                                                branch: branch.id,
                                                            }),
                                                            {},
                                                            {
                                                                preserveScroll:
                                                                    true,
                                                                onFinish: () =>
                                                                    setActionLoading(
                                                                        false,
                                                                    ),
                                                            },
                                                        );
                                                    }}
                                                >
                                                    Reactivar
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <Modal
                open={createOpen}
                onOpenChange={setCreateOpen}
                title="Nueva sucursal"
                description="Captura la ubicación y el horario de esta sucursal."
                className="flex max-h-[94vh] w-[min(96vw,80rem)] flex-col overflow-y-auto sm:max-w-6xl"
            >
                <Form
                    {...store.form(businessId)}
                    className="space-y-4"
                    options={{ preserveScroll: true }}
                    onSuccess={() => setCreateOpen(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <BranchFormFields
                                weekdays={weekdays}
                                defaultOpeningHours={defaultOpeningHours}
                                errors={errors}
                            />
                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setCreateOpen(false)}
                                >
                                    Cancelar
                                </Button>
                                <Button type="submit" loading={processing}>
                                    Crear sucursal
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </Modal>

            <Modal
                open={editing !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditing(null);
                    }
                }}
                title="Editar sucursal"
                className="flex max-h-[94vh] w-[min(96vw,80rem)] flex-col overflow-y-auto sm:max-w-6xl"
            >
                {editing ? (
                    <Form
                        {...update.form({
                            business: businessId,
                            branch: editing.id,
                        })}
                        className="space-y-4"
                        options={{ preserveScroll: true }}
                        onSuccess={() => setEditing(null)}
                    >
                        {({ processing, errors }) => (
                            <>
                                <BranchFormFields
                                    key={editing.id}
                                    branch={editing}
                                    weekdays={weekdays}
                                    defaultOpeningHours={defaultOpeningHours}
                                    errors={errors}
                                />
                                <div className="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setEditing(null)}
                                    >
                                        Cancelar
                                    </Button>
                                    <Button type="submit" loading={processing}>
                                        Guardar
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                ) : null}
            </Modal>

            <ConfirmDialog
                open={pendingDeactivate !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPendingDeactivate(null);
                    }
                }}
                title="Desactivar sucursal"
                description={`¿Desactivar "${pendingDeactivate?.name ?? ''}"? No se eliminará.`}
                confirmLabel="Desactivar"
                variant="danger"
                loading={actionLoading}
                onConfirm={() => {
                    if (!pendingDeactivate) {
                        return;
                    }

                    setActionLoading(true);
                    router.post(
                        deactivate.url({
                            business: businessId,
                            branch: pendingDeactivate.id,
                        }),
                        {},
                        {
                            preserveScroll: true,
                            onFinish: () => {
                                setActionLoading(false);
                                setPendingDeactivate(null);
                            },
                        },
                    );
                }}
            />
        </div>
    );
}
