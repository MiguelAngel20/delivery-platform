import { Form } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { BranchSingleSelect } from '@/apps/business/components/branch-single-select';
import { FormField } from '@/components/forms/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    resolveFieldError,
    validateEmployeeForm,
    type EmployeeFormClientErrors,
} from '@/lib/business/validate-employee-form';

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
    const [role, setRole] = useState(employee?.role ?? 'business_employee');
    const [branchId, setBranchId] = useState<number | null>(
        employee?.branch_ids[0] ??
            (options.branches.length === 1 ? options.branches[0].id : null),
    );
    const [clientErrors, setClientErrors] = useState<EmployeeFormClientErrors>(
        {},
    );
    const formRef = useRef<{ getData: () => Record<string, unknown> } | null>(
        null,
    );

    const isAdmin = role === 'business_admin';

    useEffect(() => {
        setClientErrors({});
    }, [employee?.id]);

    function clearFieldError(key: string) {
        setClientErrors((current) => {
            const next = { ...current };
            delete next[key];

            return next;
        });
    }

    function validateBeforeSubmit(): boolean {
        const data = formRef.current?.getData() ?? {};
        const validationErrors = validateEmployeeForm({
            firstName: String(data.first_name ?? ''),
            lastName: String(data.last_name ?? ''),
            email: String(data.email ?? ''),
            phone: String(data.phone ?? ''),
            role: String(data.role ?? role),
            status: String(data.status ?? ''),
            branchId,
        });

        if (Object.keys(validationErrors).length > 0) {
            setClientErrors(validationErrors);

            return false;
        }

        setClientErrors({});

        return true;
    }

    return (
        <Form
            ref={formRef}
            action={action.url}
            method={action.method}
            className="space-y-6"
            noValidate
            onBefore={() => validateBeforeSubmit()}
        >
            {({ processing, errors }) => (
                <>
                    {Object.keys(clientErrors).length > 0 ? (
                        <div
                            role="alert"
                            className="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                        >
                            Revisa los campos marcados antes de continuar.
                        </div>
                    ) : null}

                    <div className="grid gap-4 md:grid-cols-2">
                        <FormField
                            label="Nombre"
                            htmlFor="first_name"
                            required
                            error={resolveFieldError(
                                'first_name',
                                clientErrors,
                                errors,
                            )}
                        >
                            <Input
                                id="first_name"
                                name="first_name"
                                maxLength={100}
                                defaultValue={employee?.first_name ?? ''}
                                onChange={() => clearFieldError('first_name')}
                            />
                        </FormField>
                        <FormField
                            label="Apellidos"
                            htmlFor="last_name"
                            required
                            error={resolveFieldError(
                                'last_name',
                                clientErrors,
                                errors,
                            )}
                        >
                            <Input
                                id="last_name"
                                name="last_name"
                                maxLength={100}
                                defaultValue={employee?.last_name ?? ''}
                                onChange={() => clearFieldError('last_name')}
                            />
                        </FormField>
                        <FormField
                            label="Correo"
                            htmlFor="email"
                            required
                            error={resolveFieldError(
                                'email',
                                clientErrors,
                                errors,
                            )}
                        >
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                maxLength={255}
                                defaultValue={employee?.email ?? ''}
                                onChange={() => clearFieldError('email')}
                            />
                        </FormField>
                        <FormField
                            label="Teléfono"
                            htmlFor="phone"
                            required
                            error={resolveFieldError(
                                'phone',
                                clientErrors,
                                errors,
                            )}
                        >
                            <Input
                                id="phone"
                                name="phone"
                                maxLength={30}
                                defaultValue={employee?.phone ?? ''}
                                onChange={() => clearFieldError('phone')}
                            />
                        </FormField>
                        <FormField
                            label="Rol"
                            htmlFor="role"
                            required
                            error={resolveFieldError(
                                'role',
                                clientErrors,
                                errors,
                            )}
                        >
                            <select
                                id="role"
                                name="role"
                                value={role}
                                onChange={(event) => {
                                    setRole(event.target.value);
                                    clearFieldError('role');
                                }}
                                className="flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
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
                            error={resolveFieldError(
                                'status',
                                clientErrors,
                                errors,
                            )}
                        >
                            <select
                                id="status"
                                name="status"
                                defaultValue={employee?.status ?? 'active'}
                                onChange={() => clearFieldError('status')}
                                className="flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
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
                            label="Sucursal asignada"
                            htmlFor="branch_ids"
                            required
                            error={resolveFieldError(
                                'branch_ids',
                                clientErrors,
                                errors,
                            )}
                            hint={
                                isAdmin
                                    ? 'Cada administrador pertenece a una sola sucursal.'
                                    : 'Cada empleado pertenece a una sola sucursal.'
                            }
                            className="md:col-span-2"
                        >
                            <BranchSingleSelect
                                options={options.branches}
                                value={branchId}
                                onChange={(value) => {
                                    setBranchId(value);
                                    clearFieldError('branch_ids');
                                }}
                            />
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
