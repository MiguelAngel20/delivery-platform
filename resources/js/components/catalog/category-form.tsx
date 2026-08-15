import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { FormField } from '@/components/forms/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

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
    return (
        <Form
            action={action.url}
            method={action.method}
            className="space-y-6"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-4 md:grid-cols-2">
                        <FormField
                            label="Sucursal"
                            htmlFor="branch_id"
                            required
                            error={errors.branch_id}
                        >
                            <select
                                id="branch_id"
                                name="branch_id"
                                required
                                disabled={lockBranch || Boolean(category?.id)}
                                defaultValue={category?.branch_id ?? ''}
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
                            label="Orden"
                            htmlFor="sort_order"
                            error={errors.sort_order}
                        >
                            <Input
                                id="sort_order"
                                name="sort_order"
                                type="number"
                                min={0}
                                defaultValue={category?.sort_order ?? 0}
                            />
                        </FormField>
                        <FormField
                            label="Nombre"
                            htmlFor="name"
                            required
                            error={errors.name}
                            className="md:col-span-2"
                        >
                            <Input
                                id="name"
                                name="name"
                                required
                                maxLength={100}
                                defaultValue={category?.name ?? ''}
                            />
                        </FormField>
                        <FormField
                            label="Descripción"
                            htmlFor="description"
                            error={errors.description}
                            className="md:col-span-2"
                        >
                            <Textarea
                                id="description"
                                name="description"
                                rows={3}
                                defaultValue={category?.description ?? ''}
                            />
                        </FormField>
                        <FormField label="Estado" htmlFor="is_active">
                            <label className="flex min-h-10 items-center gap-2 text-sm">
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
