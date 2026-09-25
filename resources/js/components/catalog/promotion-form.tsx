import { Form } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import {
    mapApiOptionGroupsToDrafts,
    type ProductOptionGroupApi,
} from '@/components/catalog/product-option-group-types';
import type { ProductOptionGroupDraft } from '@/components/catalog/product-option-group-types';
import {
    clonePromotionItem,
    createEmptyPromotionItem,
    PromotionItemDialog,
    PromotionItemList,
} from '@/components/catalog/promotion-item-dialog';
import {
    PromotionRecurringHoursFields,
    type PromotionRecurringHour,
} from '@/components/catalog/promotion-recurring-hours-fields';
import { FormField } from '@/components/forms/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    resolveFieldError,
    serializePromotionItems,
    validatePromotionForm,
    type PromotionFormClientErrors,
} from '@/lib/catalog/validate-promotion-form';

export type PromotionItemDraft = {
    is_external_item: boolean;
    product_id?: string | null;
    name: string;
    description?: string;
    quantity: string;
    original_price?: string;
    option_groups?: ProductOptionGroupDraft[];
};

export type PromotionFormValues = {
    id?: number;
    branch_id: string;
    name: string;
    description?: string | null;
    promotion_price: string;
    starts_at?: string | null;
    ends_at?: string | null;
    is_recurring?: boolean;
    recurrence_starts_on?: string | null;
    recurrence_ends_on?: string | null;
    recurring_hours?: PromotionRecurringHour[];
    status: string;
    image_url?: string | null;
    items?: PromotionItemDraft[];
};

type PromotionFormProps = {
    options: CatalogFormOptions;
    promotion?: PromotionFormValues;
    action: { url: string; method: 'post' };
    submitLabel: string;
    cancelSlot?: ReactNode;
};

function normalizeOptionGroups(
    groups: ProductOptionGroupDraft[] | undefined,
): ProductOptionGroupDraft[] {
    if (!groups?.length) {
        return [];
    }

    return mapApiOptionGroupsToDrafts(groups as ProductOptionGroupApi[]);
}

export function PromotionForm({
    options,
    promotion,
    action,
    submitLabel,
    cancelSlot,
}: PromotionFormProps) {
    const [branchId, setBranchId] = useState(promotion?.branch_id ?? '');
    const [items, setItems] = useState<PromotionItemDraft[]>(
        promotion?.items?.length
            ? promotion.items.map((item) => ({
                  ...item,
                  option_groups: item.is_external_item
                      ? normalizeOptionGroups(item.option_groups)
                      : undefined,
              }))
            : [],
    );
    const [clientErrors, setClientErrors] = useState<PromotionFormClientErrors>(
        {},
    );
    const [isRecurring, setIsRecurring] = useState(
        Boolean(promotion?.is_recurring),
    );
    const [itemDialogOpen, setItemDialogOpen] = useState(false);
    const [editingIndex, setEditingIndex] = useState<number | null>(null);
    const [draftItem, setDraftItem] = useState<PromotionItemDraft>(
        createEmptyPromotionItem(),
    );
    const itemsInputRef = useRef<HTMLInputElement>(null);
    const formRef = useRef<{ getData: () => Record<string, unknown> } | null>(
        null,
    );

    useEffect(() => {
        setClientErrors({});
    }, [promotion?.id]);

    const products = options.products.filter(
        (product) => String(product.branch_id) === branchId,
    );

    function clearFieldError(key: string) {
        setClientErrors((current) => {
            const next = { ...current };
            delete next[key];

            return next;
        });
    }

    function remapItemErrors(
        errors: PromotionFormClientErrors,
        removedIndex: number,
    ): PromotionFormClientErrors {
        const next: PromotionFormClientErrors = {};

        Object.entries(errors).forEach(([key, value]) => {
            if (key === 'items' || !key.startsWith('items.')) {
                next[key] = value;

                return;
            }

            const match = /^items\.(\d+)(.*)$/.exec(key);

            if (match === null) {
                next[key] = value;

                return;
            }

            const itemIndex = Number(match[1]);
            const suffix = match[2];

            if (itemIndex < removedIndex) {
                next[key] = value;
            } else if (itemIndex > removedIndex) {
                next[`items.${itemIndex - 1}${suffix}`] = value;
            }
        });

        return next;
    }

    function validateBeforeSubmit(): boolean {
        const data = formRef.current?.getData() ?? {};
        const validationErrors = validatePromotionForm({
            branchId,
            name: String(data.name ?? ''),
            promotionPrice: String(data.promotion_price ?? ''),
            status: String(data.status ?? ''),
            startsAt: String(data.starts_at ?? ''),
            endsAt: String(data.ends_at ?? ''),
            isRecurring,
            recurrenceStartsOn: String(data.recurrence_starts_on ?? ''),
            recurrenceEndsOn: String(data.recurrence_ends_on ?? ''),
            recurringHoursRaw: String(data.recurring_hours ?? ''),
            isEditing: Boolean(promotion?.id),
            items,
        });

        if (Object.keys(validationErrors).length > 0) {
            setClientErrors(validationErrors);

            return false;
        }

        setClientErrors({});

        if (itemsInputRef.current) {
            itemsInputRef.current.value = JSON.stringify(
                serializePromotionItems(items),
            );
        }

        return true;
    }

    function openAddDialog() {
        setEditingIndex(null);
        setDraftItem(createEmptyPromotionItem());
        setItemDialogOpen(true);
        clearFieldError('items');
    }

    function openEditDialog(index: number) {
        setEditingIndex(index);
        setDraftItem(clonePromotionItem(items[index]));
        setItemDialogOpen(true);
    }

    function saveDialogItem() {
        if (editingIndex === null) {
            setItems((current) => [...current, clonePromotionItem(draftItem)]);
        } else {
            setItems((current) =>
                current.map((item, index) =>
                    index === editingIndex
                        ? clonePromotionItem(draftItem)
                        : item,
                ),
            );
        }

        setItemDialogOpen(false);
        clearFieldError('items');
    }

    function removeItem(index: number) {
        setItems((current) => current.filter((_, i) => i !== index));
        setClientErrors((current) => remapItemErrors(current, index));
    }

    return (
        <Form
            ref={formRef}
            action={action.url}
            method={action.method}
            encType="multipart/form-data"
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

                    <input
                        ref={itemsInputRef}
                        type="hidden"
                        name="items"
                        value={JSON.stringify(items)}
                    />

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
                                disabled={Boolean(promotion?.id)}
                                value={branchId}
                                onChange={(event) => {
                                    setBranchId(event.target.value);
                                    clearFieldError('branch_id');
                                }}
                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Selecciona sucursal</option>
                                {options.branches.map((branch) => (
                                    <option key={branch.value} value={branch.value}>
                                        {branch.label}
                                    </option>
                                ))}
                            </select>
                            {promotion?.id ? (
                                <input type="hidden" name="branch_id" value={branchId} />
                            ) : null}
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
                                defaultValue={promotion?.status ?? 'draft'}
                                onChange={() => clearFieldError('status')}
                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                {options.promotion_statuses.map((status) => (
                                    <option key={status.value} value={status.value}>
                                        {status.label}
                                    </option>
                                ))}
                            </select>
                        </FormField>

                        <FormField
                            label="Nombre"
                            htmlFor="name"
                            required
                            error={resolveFieldError('name', clientErrors, errors)}
                            className="md:col-span-2"
                        >
                            <Input
                                id="name"
                                name="name"
                                maxLength={150}
                                defaultValue={promotion?.name ?? ''}
                                onChange={() => clearFieldError('name')}
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
                                defaultValue={promotion?.description ?? ''}
                            />
                        </FormField>

                        <FormField
                            label="Precio promocional (MXN)"
                            htmlFor="promotion_price"
                            required
                            error={resolveFieldError(
                                'promotion_price',
                                clientErrors,
                                errors,
                            )}
                        >
                            <Input
                                id="promotion_price"
                                name="promotion_price"
                                type="number"
                                step="0.01"
                                min="0"
                                defaultValue={promotion?.promotion_price ?? ''}
                                onChange={() => clearFieldError('promotion_price')}
                            />
                        </FormField>

                        <FormField
                            label="Imagen"
                            htmlFor="image"
                            hint="Cuadrada recomendada: 1080×1080 px (mín. 800×800). Se muestra a recorte cuadrado en la app. JPG, PNG o WebP. Máx. 2 MB."
                            error={resolveFieldError('image', clientErrors, errors)}
                        >
                            <Input id="image" name="image" type="file" accept="image/*" />
                        </FormField>

                        <div className="md:col-span-2 space-y-4 rounded-lg border border-border p-4">
                            <input
                                type="hidden"
                                name="is_recurring"
                                value={isRecurring ? '1' : '0'}
                            />
                            <label className="flex items-start gap-3">
                                <Checkbox
                                    checked={isRecurring}
                                    onCheckedChange={(checked) => {
                                        setIsRecurring(checked === true);
                                        clearFieldError('is_recurring');
                                        clearFieldError('starts_at');
                                        clearFieldError('ends_at');
                                        clearFieldError('recurrence_starts_on');
                                        clearFieldError('recurrence_ends_on');
                                        clearFieldError('recurring_hours');
                                    }}
                                />
                                <span className="space-y-0.5">
                                    <span className="block text-sm font-medium">
                                        Programación recurrente
                                    </span>
                                    <span className="block text-sm text-muted-foreground">
                                        Se muestra en días y horarios que tú
                                        elijas, de forma repetida cada semana.
                                    </span>
                                </span>
                            </label>

                            {!isRecurring ? (
                                <div className="grid gap-4 md:grid-cols-2">
                                    <FormField
                                        label="Inicio"
                                        htmlFor="starts_at"
                                        error={resolveFieldError(
                                            'starts_at',
                                            clientErrors,
                                            errors,
                                        )}
                                    >
                                        <Input
                                            id="starts_at"
                                            name="starts_at"
                                            type="datetime-local"
                                            defaultValue={
                                                promotion?.starts_at
                                                    ? promotion.starts_at.slice(
                                                          0,
                                                          16,
                                                      )
                                                    : ''
                                            }
                                            onChange={() => {
                                                clearFieldError('starts_at');
                                                clearFieldError('ends_at');
                                            }}
                                            className="bg-background scheme-light dark:scheme-dark"
                                        />
                                    </FormField>
                                    <FormField
                                        label="Fin"
                                        htmlFor="ends_at"
                                        error={resolveFieldError(
                                            'ends_at',
                                            clientErrors,
                                            errors,
                                        )}
                                    >
                                        <Input
                                            id="ends_at"
                                            name="ends_at"
                                            type="datetime-local"
                                            defaultValue={
                                                promotion?.ends_at
                                                    ? promotion.ends_at.slice(
                                                          0,
                                                          16,
                                                      )
                                                    : ''
                                            }
                                            onChange={() =>
                                                clearFieldError('ends_at')
                                            }
                                            className="bg-background scheme-light dark:scheme-dark"
                                        />
                                    </FormField>
                                </div>
                            ) : (
                                <div className="grid gap-4 md:grid-cols-2">
                                    <FormField
                                        label="Inicio de recurrencia"
                                        htmlFor="recurrence_starts_on"
                                        required
                                        error={resolveFieldError(
                                            'recurrence_starts_on',
                                            clientErrors,
                                            errors,
                                        )}
                                    >
                                        <Input
                                            id="recurrence_starts_on"
                                            name="recurrence_starts_on"
                                            type="date"
                                            defaultValue={
                                                promotion?.recurrence_starts_on ??
                                                ''
                                            }
                                            onChange={() => {
                                                clearFieldError(
                                                    'recurrence_starts_on',
                                                );
                                                clearFieldError(
                                                    'recurrence_ends_on',
                                                );
                                            }}
                                        />
                                    </FormField>
                                    <FormField
                                        label="Fin de recurrencia (opcional)"
                                        htmlFor="recurrence_ends_on"
                                        hint="Si lo dejas vacío, continúa hasta que la desactives."
                                        error={resolveFieldError(
                                            'recurrence_ends_on',
                                            clientErrors,
                                            errors,
                                        )}
                                    >
                                        <Input
                                            id="recurrence_ends_on"
                                            name="recurrence_ends_on"
                                            type="date"
                                            defaultValue={
                                                promotion?.recurrence_ends_on ??
                                                ''
                                            }
                                            onChange={() =>
                                                clearFieldError(
                                                    'recurrence_ends_on',
                                                )
                                            }
                                        />
                                    </FormField>

                                    {(options.weekdays?.length ?? 0) > 0 ? (
                                        <PromotionRecurringHoursFields
                                            weekdays={options.weekdays ?? []}
                                            defaultHours={
                                                options.default_recurring_hours ??
                                                []
                                            }
                                            value={promotion?.recurring_hours}
                                            errors={{
                                                ...errors,
                                                ...clientErrors,
                                            }}
                                        />
                                    ) : (
                                        <p className="text-sm text-destructive md:col-span-2">
                                            No se pudieron cargar los días de la
                                            semana.
                                        </p>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>

                    <PromotionItemList
                        items={items}
                        clientErrors={clientErrors}
                        serverErrors={errors}
                        onAdd={openAddDialog}
                        onEdit={openEditDialog}
                        onRemove={removeItem}
                        addDisabled={branchId.trim() === ''}
                    />

                    <PromotionItemDialog
                        open={itemDialogOpen}
                        onOpenChange={setItemDialogOpen}
                        mode={editingIndex === null ? 'create' : 'edit'}
                        item={draftItem}
                        onItemChange={setDraftItem}
                        products={products}
                        onSave={saveDialogItem}
                    />

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
