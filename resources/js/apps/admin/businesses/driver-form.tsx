import { Form } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { BranchMultiSelect } from '@/apps/business/components/branch-multi-select';
import { FormField } from '@/components/forms/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export type BusinessDriverFormOptions = {
    branches: Array<{
        id: number;
        name: string;
        status: string;
        status_label: string;
    }>;
};

export type BusinessDriverFormValues = {
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    branch_ids: number[];
};

type BusinessDriverFormProps = {
    options: BusinessDriverFormOptions;
    driver?: BusinessDriverFormValues;
    action: {
        url: string;
        method: 'post' | 'put' | 'patch' | 'delete' | 'get';
    };
    submitLabel: string;
    cancelSlot?: ReactNode;
};

export function BusinessDriverForm({
    options,
    driver,
    action,
    submitLabel,
    cancelSlot,
}: BusinessDriverFormProps) {
    const [branchIds, setBranchIds] = useState<number[]>(
        driver?.branch_ids ?? [],
    );

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
                                defaultValue={driver?.first_name ?? ''}
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
                                defaultValue={driver?.last_name ?? ''}
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
                                defaultValue={driver?.email ?? ''}
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
                                defaultValue={driver?.phone ?? ''}
                            />
                        </FormField>
                        <FormField
                            label="Sucursales"
                            htmlFor="branch_ids"
                            required
                            error={errors.branch_ids}
                            hint="Solo le llegarán pedidos de las sucursales que selecciones."
                            className="md:col-span-2"
                        >
                            <BranchMultiSelect
                                options={options.branches}
                                value={branchIds}
                                onChange={setBranchIds}
                            />
                        </FormField>
                    </div>

                    <p className="rounded-md border border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                        El repartidor entra con su correo y la contraseña
                        temporal{' '}
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
