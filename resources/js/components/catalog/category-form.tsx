import { Form } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import {
    PromotionRecurringHoursFields,
    type PromotionRecurringHour,
} from '@/components/catalog/promotion-recurring-hours-fields';
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
    categories: Array<{
        value: string;
        label: string;
        branch_id: number;
        parent_id?: number | null;
        is_root?: boolean;
    }>;
    parent_categories?: Array<{
        value: string;
        label: string;
        branch_id: number;
    }>;
    products: Array<{ value: string; label: string; branch_id: number }>;
    product_images?: Array<{ path: string; url: string }>;
    option_group_types: Array<{ value: string; label: string }>;
    promotion_statuses: Array<{ value: string; label: string }>;
    weekdays?: Array<{ value: string; label: string }>;
    default_recurring_hours?: Array<{
        day: string;
        is_open: boolean;
        opens_at: string | null;
        closes_at: string | null;
    }>;
    default_schedule_hours?: Array<{
        day: string;
        is_open: boolean;
        opens_at: string | null;
        closes_at: string | null;
    }>;
};

export type CategoryFormValues = {
    id?: number;
    branch_id: string;
    parent_id?: string | null;
    name: string;
    description?: string | null;
    sort_order?: number;
    is_active?: boolean;
    has_schedule?: boolean;
    schedule_hours?: PromotionRecurringHour[];
};

type CategoryFormProps = {
    options: CatalogFormOptions;
    category?: CategoryFormValues;
    action: { url: string; method: 'post' | 'put' };
    submitLabel: string;
    cancelSlot?: ReactNode;
    lockBranch?: boolean;
    /** principal = categoría del menú; subcategory = dentro de una categoría */
    variant?: 'principal' | 'subcategory';
};

export function CategoryForm({
    options,
    category,
    action,
    submitLabel,
    cancelSlot,
    lockBranch = false,
    variant = 'principal',
}: CategoryFormProps) {
    const [clientErrors, setClientErrors] = useState<CategoryFormClientErrors>(
        {},
    );
    const [branchId, setBranchId] = useState(category?.branch_id ?? '');
    const [hasSchedule, setHasSchedule] = useState(
        category?.has_schedule ?? false,
    );
    const formRef = useRef<{ getData: () => Record<string, unknown> } | null>(
        null,
    );

    useEffect(() => {
        setClientErrors({});
        setBranchId(category?.branch_id ?? '');
        setHasSchedule(category?.has_schedule ?? false);
    }, [category?.id, category?.branch_id, category?.has_schedule]);

    const parentOptions = useMemo(() => {
        const parents = options.parent_categories ?? [];

        return parents.filter((parent) => {
            if (branchId === '') {
                return true;
            }

            if (category?.id && parent.value === String(category.id)) {
                return false;
            }

            return String(parent.branch_id) === branchId;
        });
    }, [options.parent_categories, branchId, category?.id]);

    const weekdays = options.weekdays ?? [];
    const defaultScheduleHours =
        options.default_schedule_hours ?? options.default_recurring_hours ?? [];

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
                                value={branchId}
                                onChange={(event) => {
                                    setBranchId(event.target.value);
                                    setClientErrors((current) => {
                                        const next = { ...current };
                                        delete next.branch_id;

                                        return next;
                                    });
                                }}
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
                            {(lockBranch || category?.id) &&
                            category?.branch_id ? (
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
                            error={resolveFieldError(
                                'name',
                                clientErrors,
                                errors,
                            )}
                        >
                            <Input
                                id="name"
                                name="name"
                                maxLength={100}
                                placeholder={
                                    variant === 'subcategory'
                                        ? 'Ej. Pizza, Bebidas, Hamburguesas'
                                        : 'Ej. Homa, Pizza, Bebidas'
                                }
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
                        {variant === 'subcategory' ? (
                            <FormField
                                label="Categoría principal"
                                htmlFor="parent_id"
                                required
                                error={resolveFieldError(
                                    'parent_id',
                                    clientErrors,
                                    errors,
                                )}
                                className="md:col-span-2"
                            >
                                <select
                                    id="parent_id"
                                    name="parent_id"
                                    defaultValue={category?.parent_id ?? ''}
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    <option value="">
                                        Selecciona la categoría principal
                                    </option>
                                    {parentOptions.map((parent) => (
                                        <option
                                            key={parent.value}
                                            value={parent.value}
                                        >
                                            {parent.label}
                                        </option>
                                    ))}
                                </select>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    La subcategoría pertenece a una categoría
                                    principal (por ejemplo Pizza dentro de Homa,
                                    o Especialidades dentro de Pizza).
                                </p>
                            </FormField>
                        ) : (
                            <input type="hidden" name="parent_id" value="" />
                        )}
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
                                    type="hidden"
                                    name="is_active"
                                    value="0"
                                />
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
                        {variant === 'principal' ? (
                            <FormField
                                label="Horario programado"
                                htmlFor="has_schedule"
                                className="md:col-span-2"
                                error={resolveFieldError(
                                    'has_schedule',
                                    clientErrors,
                                    errors,
                                )}
                            >
                                <input
                                    type="hidden"
                                    name="has_schedule"
                                    value="0"
                                />
                                <label className="flex items-start gap-2 text-sm text-foreground">
                                    <input
                                        id="has_schedule"
                                        name="has_schedule"
                                        type="checkbox"
                                        value="1"
                                        checked={hasSchedule}
                                        onChange={(event) =>
                                            setHasSchedule(
                                                event.target.checked,
                                            )
                                        }
                                        className="mt-0.5"
                                    />
                                    <span>
                                        Limitar visibilidad a un horario
                                        <span className="mt-1 block text-xs text-muted-foreground">
                                            Opcional. Si no lo activas, la
                                            categoría se muestra siempre
                                            (mientras esté activa).
                                        </span>
                                    </span>
                                </label>
                            </FormField>
                        ) : null}
                        {variant === 'principal' &&
                        hasSchedule &&
                        weekdays.length > 0 &&
                        defaultScheduleHours.length > 0 ? (
                            <div className="md:col-span-2">
                                <PromotionRecurringHoursFields
                                    weekdays={weekdays}
                                    defaultHours={defaultScheduleHours}
                                    value={category?.schedule_hours}
                                    errors={errors}
                                    fieldName="schedule_hours"
                                    title="Días y horarios de la categoría"
                                    description="La categoría y sus productos solo se mostrarán en el menú dentro de estos horarios (por ejemplo Desayuno 08:00–13:00)."
                                />
                            </div>
                        ) : null}
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
