import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { BranchList } from '@/apps/admin/businesses/branch-list';
import {
    businessStatusTone
} from '@/apps/admin/businesses/types';
import type {BusinessDetail, BusinessFormOptions} from '@/apps/admin/businesses/types';
import { StatusBadge } from '@/components/data-display/status-badge';
import { ConfirmDialog, Modal } from '@/components/dialogs/modal';
import { FormField } from '@/components/forms/form-field';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import admin from '@/routes/admin';
import {
    activate,
    approve,
    edit,
    index,
    reject,
    suspend,
} from '@/routes/admin/businesses';
import { update as updateLimits } from '@/routes/admin/businesses/limits';
import {
    approve as approveUpgrade,
    reject as rejectUpgrade,
} from '@/routes/admin/businesses/upgrade-requests';
import {
    create as createBusinessUser,
    edit as editBusinessUser,
    index as businessUsersIndex,
} from '@/routes/admin/businesses/users';

type Props = {
    business: BusinessDetail;
    options: BusinessFormOptions;
    limits: {
        max_branches: number;
        max_business_admins: number;
        max_employees_per_branch: number;
        branches_used: number;
        business_admins_used: number;
        can_create_branch: boolean;
        can_add_business_admin: boolean;
        branch_employee_usage: Array<{
            branch_id: number;
            branch_name: string;
            used: number;
            max: number;
            remaining: number;
        }>;
    };
    upgradeRequests: Array<{
        id: number;
        type: string;
        type_label: string;
        requested_quantity: number;
        status: string;
        status_label: string;
        notes: string | null;
        branch: { id: number; name: string } | null;
        requested_by: { id: number; name: string } | null;
        created_at: string | null;
    }>;
};

export default function AdminBusinessesShow({
    business,
    limits,
    upgradeRequests,
}: Props) {
    const [loading, setLoading] = useState(false);
    const [rejectOpen, setRejectOpen] = useState(false);
    const [suspendOpen, setSuspendOpen] = useState(false);
    const [activateOpen, setActivateOpen] = useState(false);
    const [approveOpen, setApproveOpen] = useState(false);
    const [reason, setReason] = useState('');

    const postAction = (url: string, data: Record<string, string> = {}) => {
        setLoading(true);
        router.post(url, data, {
            preserveScroll: true,
            onFinish: () => {
                setLoading(false);
                setRejectOpen(false);
                setSuspendOpen(false);
                setActivateOpen(false);
                setApproveOpen(false);
                setReason('');
            },
        });
    };

    return (
        <>
            <Head title={business.name} />
            <PageContainer>
                <PageHeader
                    title={business.name}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={index.url()}>Volver</Link>
                            </Button>
                            <Button asChild>
                                <Link href={edit.url(business.id)}>Editar</Link>
                            </Button>
                            {business.status === 'pending_approval' ? (
                                <>
                                    <Button
                                        type="button"
                                        onClick={() => setApproveOpen(true)}
                                    >
                                        Aprobar
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="danger"
                                        onClick={() => setRejectOpen(true)}
                                    >
                                        Rechazar
                                    </Button>
                                </>
                            ) : null}
                            {business.status === 'active' ? (
                                <Button
                                    type="button"
                                    variant="danger"
                                    onClick={() => setSuspendOpen(true)}
                                >
                                    Suspender
                                </Button>
                            ) : null}
                            {business.status === 'suspended' ? (
                                <Button
                                    type="button"
                                    onClick={() => setActivateOpen(true)}
                                >
                                    Reactivar
                                </Button>
                            ) : null}
                        </>
                    }
                />

                <div className="grid gap-6 lg:grid-cols-3">
                    <ContentCard
                        title="Información general"
                        className="lg:col-span-2"
                    >
                        <dl className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    Estado
                                </dt>
                                <dd className="mt-1">
                                    <StatusBadge
                                        tone={businessStatusTone(
                                            business.status,
                                        )}
                                    >
                                        {business.status_label}
                                    </StatusBadge>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    Tipo
                                </dt>
                                <dd className="mt-1 font-medium">
                                    {business.operation_mode_label}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    Modalidad de entrega
                                </dt>
                                <dd className="mt-1 font-medium">
                                    {business.delivery_mode_label}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    Giro
                                </dt>
                                <dd className="mt-1 font-medium">
                                    {business.business_type ?? '—'}
                                </dd>
                            </div>
                            <div className="sm:col-span-2">
                                <dt className="text-sm text-muted-foreground">
                                    Descripción
                                </dt>
                                <dd className="mt-1 text-sm">
                                    {business.description ?? '—'}
                                </dd>
                            </div>
                            {business.rejection_reason ? (
                                <div className="sm:col-span-2">
                                    <dt className="text-sm text-muted-foreground">
                                        Motivo de rechazo
                                    </dt>
                                    <dd className="mt-1 text-sm">
                                        {business.rejection_reason}
                                    </dd>
                                </div>
                            ) : null}
                            {business.suspension_reason ? (
                                <div className="sm:col-span-2">
                                    <dt className="text-sm text-muted-foreground">
                                        Motivo de suspensión
                                    </dt>
                                    <dd className="mt-1 text-sm">
                                        {business.suspension_reason}
                                    </dd>
                                </div>
                            ) : null}
                        </dl>
                    </ContentCard>

                    <ContentCard title="Contacto">
                        <dl className="space-y-4">
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    Teléfono
                                </dt>
                                <dd className="mt-1 font-medium">
                                    {business.phone ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    Correo
                                </dt>
                                <dd className="mt-1 font-medium">
                                    {business.email ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    Logo
                                </dt>
                                <dd className="mt-2">
                                    {business.logo_url ? (
                                        <img
                                            src={business.logo_url}
                                            alt={`Logo de ${business.name}`}
                                            className="h-16 w-16 rounded-md border object-cover"
                                        />
                                    ) : (
                                        '—'
                                    )}
                                </dd>
                            </div>
                        </dl>
                    </ContentCard>
                </div>

                <ContentCard title="Sucursales">
                    <BranchList
                        businessId={business.id}
                        branches={business.branches}
                        canCreateBranch={limits.can_create_branch}
                        branchesUsed={limits.branches_used}
                        maxBranches={limits.max_branches}
                    />
                </ContentCard>

                {business.operation_mode === 'platform_operated' ? (
                    <ContentCard
                        title="Catálogo RIDE"
                        actions={
                            <Button size="sm" asChild>
                                <Link
                                    href={`/admin/businesses/${business.id}/catalog`}
                                >
                                    Administrar catálogo
                                </Link>
                            </Button>
                        }
                    >
                        <p className="text-sm text-muted-foreground">
                            Categorías, productos y promociones de esta empresa
                            operada por RIDE.
                        </p>
                    </ContentCard>
                ) : null}

                <ContentCard
                    title="Usuarios"
                    actions={
                        <>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={businessUsersIndex.url(business.id)}>
                                    Ver todos
                                </Link>
                            </Button>
                            <Button size="sm" asChild>
                                <Link href={createBusinessUser.url(business.id)}>
                                    + Agregar usuario
                                </Link>
                            </Button>
                        </>
                    }
                >
                    {business.memberships.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Esta empresa no tiene usuarios empresariales
                            asociados.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border border-[#E2E8F0]">
                            <table className="min-w-full text-sm">
                                <thead className="bg-[#F8FAFC] text-left text-[#64748B]">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">
                                            Nombre
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Correo
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Teléfono
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Rol
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Sucursales
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Estado
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {business.memberships.map((membership) => (
                                        <tr
                                            key={membership.id}
                                            className="border-t border-[#E2E8F0]"
                                        >
                                            <td className="px-4 py-3 font-medium">
                                                {membership.user?.name ?? '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {membership.user?.email}
                                            </td>
                                            <td className="px-4 py-3">
                                                {membership.user?.phone ?? '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {membership.role_label}
                                            </td>
                                            <td className="px-4 py-3 text-[#475569]">
                                                {membership.role ===
                                                'business_admin'
                                                    ? 'Todas'
                                                    : membership.branches
                                                          .length > 0
                                                      ? membership.branches
                                                            .map(
                                                                (branch) =>
                                                                    branch.name,
                                                            )
                                                            .join(', ')
                                                      : '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {membership.status_label}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={editBusinessUser.url(
                                                            {
                                                                business:
                                                                    business.id,
                                                                businessUser:
                                                                    membership.id,
                                                            },
                                                        )}
                                                    >
                                                        Editar
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </ContentCard>

                <ContentCard title="Límites / Plan">
                    <div className="mb-4 grid gap-3 sm:grid-cols-3">
                        <p className="text-sm">
                            Sucursales:{' '}
                            <span className="font-medium text-navy">
                                {limits.branches_used} / {limits.max_branches}
                            </span>
                        </p>
                        <p className="text-sm">
                            Administradores:{' '}
                            <span className="font-medium text-navy">
                                {limits.business_admins_used} /{' '}
                                {limits.max_business_admins}
                            </span>
                        </p>
                        <p className="text-sm">
                            Empleados por sucursal:{' '}
                            <span className="font-medium text-navy">
                                hasta {limits.max_employees_per_branch}
                            </span>
                        </p>
                    </div>
                    <Form
                        {...updateLimits.form(business.id)}
                        className="grid gap-4 md:grid-cols-4"
                        options={{ preserveScroll: true }}
                    >
                        {({ processing, errors }) => (
                            <>
                                <FormField
                                    label="Máx. sucursales"
                                    htmlFor="max_branches"
                                    error={errors.max_branches}
                                >
                                    <Input
                                        id="max_branches"
                                        name="max_branches"
                                        type="number"
                                        min={1}
                                        defaultValue={limits.max_branches}
                                        required
                                    />
                                </FormField>
                                <FormField
                                    label="Máx. administradores"
                                    htmlFor="max_business_admins"
                                    error={errors.max_business_admins}
                                >
                                    <Input
                                        id="max_business_admins"
                                        name="max_business_admins"
                                        type="number"
                                        min={1}
                                        defaultValue={
                                            limits.max_business_admins
                                        }
                                        required
                                    />
                                </FormField>
                                <FormField
                                    label="Máx. empleados / sucursal"
                                    htmlFor="max_employees_per_branch"
                                    error={errors.max_employees_per_branch}
                                >
                                    <Input
                                        id="max_employees_per_branch"
                                        name="max_employees_per_branch"
                                        type="number"
                                        min={1}
                                        defaultValue={
                                            limits.max_employees_per_branch
                                        }
                                        required
                                    />
                                </FormField>
                                <div className="flex items-end">
                                    <Button type="submit" loading={processing}>
                                        Guardar límites
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </ContentCard>

                <ContentCard title="Solicitudes">
                    {upgradeRequests.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No hay solicitudes de ampliación.
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {upgradeRequests.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex flex-col gap-3 rounded-lg border border-[#E2E8F0] p-4 md:flex-row md:items-center md:justify-between"
                                >
                                    <div>
                                        <p className="font-medium text-navy">
                                            {item.type_label} · +
                                            {item.requested_quantity}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {item.branch
                                                ? `Sucursal: ${item.branch.name} · `
                                                : ''}
                                            {item.requested_by?.name ?? '—'} ·{' '}
                                            {item.status_label}
                                        </p>
                                    </div>
                                    {item.status === 'pending' ? (
                                        <div className="flex gap-2">
                                            <Button
                                                type="button"
                                                size="sm"
                                                loading={loading}
                                                onClick={() =>
                                                    postAction(
                                                        approveUpgrade.url({
                                                            business:
                                                                business.id,
                                                            upgradeRequest:
                                                                item.id,
                                                        }),
                                                        {
                                                            apply_limit_increase:
                                                                '1',
                                                            quantity: String(
                                                                item.requested_quantity,
                                                            ),
                                                        },
                                                    )
                                                }
                                            >
                                                Aprobar e incrementar
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="danger"
                                                loading={loading}
                                                onClick={() =>
                                                    postAction(
                                                        rejectUpgrade.url({
                                                            business:
                                                                business.id,
                                                            upgradeRequest:
                                                                item.id,
                                                        }),
                                                    )
                                                }
                                            >
                                                Rechazar
                                            </Button>
                                        </div>
                                    ) : null}
                                </div>
                            ))}
                        </div>
                    )}
                </ContentCard>
            </PageContainer>

            <ConfirmDialog
                open={approveOpen}
                onOpenChange={setApproveOpen}
                title="Aprobar empresa"
                description={`¿Aprobar "${business.name}" y dejarla activa?`}
                confirmLabel="Aprobar"
                loading={loading}
                onConfirm={() => postAction(approve.url(business.id))}
            />

            <ConfirmDialog
                open={activateOpen}
                onOpenChange={setActivateOpen}
                title="Reactivar empresa"
                description={`¿Reactivar "${business.name}"?`}
                confirmLabel="Reactivar"
                loading={loading}
                onConfirm={() => postAction(activate.url(business.id))}
            />

            <Modal
                open={rejectOpen}
                onOpenChange={setRejectOpen}
                title="Rechazar solicitud"
                description="Indica el motivo del rechazo."
                footer={
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setRejectOpen(false)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            variant="danger"
                            loading={loading}
                            disabled={reason.trim().length < 5}
                            onClick={() =>
                                postAction(reject.url(business.id), {
                                    reason: reason.trim(),
                                })
                            }
                        >
                            Rechazar
                        </Button>
                    </>
                }
            >
                <FormField label="Motivo" htmlFor="reject_reason" required>
                    <Textarea
                        id="reject_reason"
                        value={reason}
                        onChange={(event) => setReason(event.target.value)}
                        rows={4}
                    />
                </FormField>
            </Modal>

            <Modal
                open={suspendOpen}
                onOpenChange={setSuspendOpen}
                title="Suspender empresa"
                description="La empresa no estará disponible públicamente."
                footer={
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setSuspendOpen(false)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            variant="danger"
                            loading={loading}
                            disabled={reason.trim().length < 5}
                            onClick={() =>
                                postAction(suspend.url(business.id), {
                                    reason: reason.trim(),
                                })
                            }
                        >
                            Suspender
                        </Button>
                    </>
                }
            >
                <FormField label="Motivo" htmlFor="suspend_reason" required>
                    <Textarea
                        id="suspend_reason"
                        value={reason}
                        onChange={(event) => setReason(event.target.value)}
                        rows={4}
                    />
                </FormField>
            </Modal>
        </>
    );
}

AdminBusinessesShow.layout = {
    title: 'Detalle de empresa',
    breadcrumbs: [
        {
            title: 'Empresas',
            href: admin.businesses.index(),
        },
        {
            title: 'Detalle',
            href: '#',
        },
    ],
};
