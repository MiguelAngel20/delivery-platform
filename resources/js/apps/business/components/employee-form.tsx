import { Form } from '@inertiajs/react';
import { useState  } from 'react';
import type {ReactNode} from 'react';
import { BranchMultiSelect } from '@/apps/business/components/branch-multi-select';
import { BranchSingleSelect } from '@/apps/business/components/branch-single-select';
import { FormField } from '@/components/forms/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export type EmployeeFormOptions = {
    roles: Array<{ value: string; label: string }>;
    statuses: Array<{ value: string; label: string }>;
    branches: Array<{
        id: number;
        name: string;
        status: string;
        status_label: string;
    }>;
};

export type EmployeeFormValues = {
    id?: number;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    role: string;
    status: string;
    branch_ids: number[];
};

type EmployeeFormProps = {
    options: EmployeeFormOptions;
    employee?: EmployeeFormValues;
    action: {
        url: string;
        method: 'post' | 'put' | 'patch' | 'delete' | 'get';
    };
    submitLabel: string;
    cancelSlot?: ReactNode;
};

export function EmployeeForm({
    options,
    employee,
    action,
    submitLabel,
    cancelSlot,
}: EmployeeFormProps) {
    const [role, setRole] = useState(
        employee?.role ?? 'business_employee',
    );
    const [branchIds, setBranchIds] = useState<number[]>(
        employee?.branch_ids ?? [],
    );
    const [adminBranchId, setAdminBranchId] = useState<number | null>(
        employee?.role === 'business_admin'
            ? (employee.branch_ids[0] ?? null)
            : null,
    );

    const isAdmin = role === 'business_admin';
    const requiresBranches = !isAdmin;

    return (
        <Form action={action.url} method={action.method} className="space-y-6">
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-4 md:grid-cols-2">
                        <FormField
                            label="Nombre"
                            htmlFor="first_name"
                            required
                            error={errors.first_name}
                        >
                            <Input
                                id="first_name"
                                name="first_name"
                                required
                                defaultValue={employee?.first_name ?? ''}
                            />
                        </FormField>
                        <FormField
                            label="Apellidos"
                            htmlFor="last_name"
                            required
                            error={errors.last_name}
                        >
                            <Input
                                id="last_name"
                                name="last_name"
                                required
                                defaultValue={employee?.last_name ?? ''}
                            />
                        </FormField>
                        <FormField
                            label="Correo"
                            htmlFor="email"
                            required
                            error={errors.email}
                        >
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                required
                                defaultValue={employee?.email ?? ''}
                            />
                        </FormField>
                        <FormField
                            label="Teléfono"
                            htmlFor="phone"
                            required
                            error={errors.phone}
                        >
                            <Input
                                id="phone"
                                name="phone"
                                required
                                defaultValue={employee?.phone ?? ''}
                            />
                        </FormField>
                        <FormField
                            label="Rol"
                            htmlFor="role"
                            required
                            error={errors.role}
                        >
                            <select
                                id="role"
                                name="role"
                                required
                                value={role}
                                onChange={(event) => {
                                    const nextRole = event.target.value;
                                    setRole(nextRole);

                                    if (nextRole === 'business_admin') {
                                        setBranchIds([]);
                                        setAdminBranchId(
                                            options.branches.length === 1
                                                ? options.branches[0].id
                                                : null,
                                        );
                                    } else {
                                        setAdminBranchId(null);
                                    }
                                }}
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                {options.roles.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                        <FormField
                            label="Estado"
                            htmlFor="status"
                            required
                            error={errors.status}
                        >
                            <select
                                id="status"
                                name="status"
                                required
                                defaultValue={employee?.status ?? 'active'}
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                {options.statuses.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                        <FormField
                            label={isAdmin ? 'Sucursal asignada' : 'Sucursales asignadas'}
                            htmlFor="branch_ids"
                            required
                            error={errors.branch_ids}
                            hint={
                                isAdmin
                                    ? 'Cada administrador pertenece a una sola sucursal.'
                                    : 'Selecciona al menos una sucursal.'
                            }
                            className="md:col-span-2"
                        >
                            {isAdmin ? (
                                <BranchSingleSelect
                                    options={options.branches}
                                    value={adminBranchId}
                                    onChange={setAdminBranchId}
                                />
                            ) : (
                                <BranchMultiSelect
                                    options={options.branches}
                                    value={branchIds}
                                    onChange={setBranchIds}
                                />
                            )}
                        </FormField>
                    </div>

                    <p className="rounded-md border border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                        El usuario entra en{' '}
                        <span className="font-medium text-foreground">
                            Acceso negocio
                        </span>{' '}
                        con su correo y la contraseña temporal{' '}
                        <span className="font-medium text-foreground">
                            12344321
                        </span>
                        . Al iniciar sesión deberá cambiarla.
                    </p>

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
