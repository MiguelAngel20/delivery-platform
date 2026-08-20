import { Form } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { FormField } from '@/components/forms/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    resolveFieldError,
    validateCategoryForm,
    type CategoryFormClientErrors,
} from '@/lib/catalog/validate-category-form';

export type CatalogFormOptions = {
    branches: Array<{ value: string; label: string }>;
    categories: Array<{ value: string; label: string; branch_id: number }>;
    products: Array<{ value: string; label: string; branch_id: number }>;
    option_group_types: Array<{ value: string; label: string }>;
    promotion_statuses: Array<{ value: string; label: string }>;
};

export type CategoryFormValues = {
    id?: number;
    branch_id: string;
    name: string;
    description?: string | null;
    sort_order?: number;
    is_active?: boolean;
};

type CategoryFormProps = {
    options: CatalogFormOptions;
    category?: CategoryFormValues;
    action: { url: string; method: 'post' | 'put' };
    submitLabel: string;
    cancelSlot?: ReactNode;
    lockBranch?: boolean;
};

export function CategoryForm({
    options,
    category,
    action,
    submitLabel,
    cancelSlot,
    lockBranch = false,
}: CategoryFormProps) {
    const [clientErrors, setClientErrors] = useState<CategoryFormClientErrors>(
        {},
    );
    const formRef = useRef<{ getData: () => Record<string, unknown> } | null>(
        null,
    );

    useEffect(() => {
        setClientErrors({});
    }, [category?.id]);

    function validateBeforeSubmit(): boolean {
        const data = formRef.current?.getData() ?? {};
        const validationErrors = validateCategoryForm({
            branchId: String(data.branch_id ?? ''),
            name: String(data.name ?? ''),
            requiresBranch: !lockBranch && !category?.id,
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
                            label="Sucursal"
                            htmlFor="branch_id"
                            required
                            error={resolveFieldError(
                                'branch_id',
                                clientErrors,
                                errors,
                            )}
                        >
                            <select
                                id="branch_id"
                                name="branch_id"
                                disabled={lockBranch || Boolean(category?.id)}
                                defaultValue={category?.branch_id ?? ''}
                                onChange={() =>
                                    setClientErrors((current) => {
                                        const next = { ...current };
                                        delete next.branch_id;

                                        return next;
                                    })
                                }
                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Selecciona sucursal</option>
                                {options.branches.map((branch) => (
                                    <option
                                        key={branch.value}
                                        value={branch.value}
                                    >
                                        {branch.label}
                                    </option>
                                ))}
                            </select>
                            {(lockBranch || category?.id) && category?.branch_id ? (
                                <input
                                    type="hidden"
                                    name="branch_id"
                                    value={category.branch_id}
                                />
                            ) : null}
                        </FormField>
                        <FormField
                            label="Nombre"
                            htmlFor="name"
                            required
                            error={resolveFieldError('name', clientErrors, errors)}
                        >
                            <Input
                                id="name"
                                name="name"
                                maxLength={100}
                                placeholder="Ej. Desayunos, Bebidas, Postres"
                                defaultValue={category?.name ?? ''}
                                onChange={() =>
                                    setClientErrors((current) => {
                                        const next = { ...current };
                                        delete next.name;

                                        return next;
                                    })
                                }
                            />
                        </FormField>
                        <FormField
                            label="Descripción"
                            htmlFor="description"
                            error={resolveFieldError(
                                'description',
                                clientErrors,
                                errors,
                            )}
                            className="md:col-span-2"
                        >
                            <Textarea
                                id="description"
                                name="description"
                                rows={3}
                                placeholder="Breve descripción de esta categoría (opcional)"
                                defaultValue={category?.description ?? ''}
                            />
                        </FormField>
                        <FormField label="Estado" htmlFor="is_active">
                            <label className="flex min-h-10 items-center gap-2 text-sm text-foreground">
                                <input
                                    id="is_active"
                                    name="is_active"
                                    type="checkbox"
                                    value="1"
                                    defaultChecked={category?.is_active ?? true}
                                />
                                Activa
                            </label>
                        </FormField>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        <Button type="submit" disabled={processing}>
                            {submitLabel}
                        </Button>
                        {cancelSlot}
                    </div>
                </>
            )}
        </Form>
    );
}
