import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import {
    mapApiOptionGroupsToDrafts,
    type ProductOptionGroupApi,
    type ProductOptionGroupDraft,
} from '@/components/catalog/product-option-group-types';
import { ProductOptionGroupsFields } from '@/components/catalog/product-option-groups-fields';
import {
    buildGroup,
    emptyOption,
    findGroupIndex,
    SECTION_CONFIG,
} from '@/components/catalog/product-option-groups-config';
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
import {
    sanitizeProductOptionGroups,
    sizeGroupMinPrice,
    validateProductOptionGroups,
} from '@/lib/catalog/validate-product-form';

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
    option_groups?: ProductOptionGroupApi[] | null;
    items?: PromotionItemDraft[];
};

type PromotionFormProps = {
    options: CatalogFormOptions;
    promotion?: PromotionFormValues;
    action: { url: string; method: 'post' };
    submitLabel: string;
    cancelSlot?: ReactNode;
};

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
                  is_external_item: true,
                  name: item.name,
                  description: item.description ?? '',
                  quantity: String(item.quantity ?? '1'),
              }))
            : [],
    );
    const [groups, setGroups] = useState<ProductOptionGroupDraft[]>(() =>
        mapApiOptionGroupsToDrafts(promotion?.option_groups ?? []),
    );
    const [promotionPrice, setPromotionPrice] = useState(
        promotion?.promotion_price ?? '',
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
    const optionGroupsInputRef = useRef<HTMLInputElement>(null);
    const formRef = useRef<{ getData: () => Record<string, unknown> } | null>(
        null,
    );

    useEffect(() => {
        setClientErrors({});
    }, [promotion?.id]);

    const sizeGroupIndex = findGroupIndex(groups, 'size');
    const sizeGroup =
        sizeGroupIndex === -1 ? undefined : groups[sizeGroupIndex];
    const sizesEnabled = sizeGroup !== undefined;
    const derivedPromotionPrice = sizeGroupMinPrice(groups);

    useEffect(() => {
        if (derivedPromotionPrice !== null) {
            setPromotionPrice(derivedPromotionPrice);
        }
    }, [derivedPromotionPrice]);

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
        const validationErrors = {
            ...validatePromotionForm({
                branchId,
                name: String(data.name ?? ''),
                promotionPrice: sizesEnabled
                    ? (derivedPromotionPrice ?? promotionPrice)
                    : promotionPrice || String(data.promotion_price ?? ''),
                status: String(data.status ?? ''),
                startsAt: String(data.starts_at ?? ''),
                endsAt: String(data.ends_at ?? ''),
                isRecurring,
                recurrenceStartsOn: String(data.recurrence_starts_on ?? ''),
                recurrenceEndsOn: String(data.recurrence_ends_on ?? ''),
                recurringHoursRaw: String(data.recurring_hours ?? ''),
                isEditing: Boolean(promotion?.id),
                items,
            }),
            ...validateProductOptionGroups(groups),
        };

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

        if (optionGroupsInputRef.current) {
            optionGroupsInputRef.current.value = JSON.stringify(
                sanitizeProductOptionGroups(groups),
            );
        }

        return true;
    }

    function toggleSizes(enabled: boolean) {
        if (enabled) {
            if (findGroupIndex(groups, 'size') !== -1) {
                return;
            }

            setGroups([...groups, buildGroup('size')]);

            return;
        }

        setGroups(groups.filter((group) => group.type !== 'size'));
    }

    function updateSizeOption(
        optionIndex: number,
        patch: Partial<ProductOptionGroupDraft['options'][number]>,
    ) {
        setGroups(
            groups.map((group) => {
                if (group.type !== 'size') {
                    return group;
                }

                return {
                    ...group,
                    options: group.options.map((option, index) =>
                        index === optionIndex ? { ...option, ...patch } : option,
                    ),
                };
            }),
        );
    }

    function addSizeOption() {
        setGroups(
            groups.map((group) =>
                group.type === 'size'
                    ? {
                          ...group,
                          options: [...group.options, emptyOption('size')],
                      }
                    : group,
            ),
        );
    }

    function removeSizeOption(optionIndex: number) {
        setGroups(
            groups.map((group) => {
                if (group.type !== 'size') {
                    return group;
                }

                const filtered = group.options.filter(
                    (_, index) => index !== optionIndex,
                );

                return {
                    ...group,
                    options:
                        filtered.length === 0
                            ? [emptyOption('size')]
                            : filtered,
                };
            }),
        );
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
                    <input
                        ref={optionGroupsInputRef}
                        type="hidden"
                        name="option_groups"
                        value={JSON.stringify(sanitizeProductOptionGroups(groups))}
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
                            label="Nombre de la promoción"
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
                            hint={
                                sizesEnabled
                                    ? 'Se toma del tamaño más económico.'
                                    : undefined
                            }
                        >
                            <Input
                                id="promotion_price"
                                name="promotion_price"
                                type="number"
                                step="0.01"
                                min="0"
                                value={promotionPrice}
                                readOnly={sizesEnabled}
                                onChange={(event) => {
                                    if (sizesEnabled) {
                                        return;
                                    }

                                    setPromotionPrice(event.target.value);
                                    clearFieldError('promotion_price');
                                }}
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

                        <div className="space-y-3 rounded-xl border border-border bg-surface p-4 md:col-span-2">
                            <label className="flex cursor-pointer items-start gap-3 text-foreground">
                                <Checkbox
                                    checked={sizesEnabled}
                                    onCheckedChange={(checked) =>
                                        toggleSizes(checked === true)
                                    }
                                    className="mt-0.5"
                                />
                                <div>
                                    <span className="text-sm font-medium">
                                        {SECTION_CONFIG.size.label}
                                    </span>
                                    <p className="text-sm text-muted-foreground">
                                        Opcional. Actívalo si la promoción tiene
                                        presentaciones con precios distintos.
                                    </p>
                                </div>
                            </label>

                            {sizesEnabled && sizeGroup ? (
                                <div className="space-y-3 border-t border-border pt-3">
                                    {resolveFieldError(
                                        `option_groups.${sizeGroupIndex}.options`,
                                        clientErrors,
                                        errors,
                                    ) ? (
                                        <p className="text-sm text-destructive">
                                            {resolveFieldError(
                                                `option_groups.${sizeGroupIndex}.options`,
                                                clientErrors,
                                                errors,
                                            )}
                                        </p>
                                    ) : null}

                                    {sizeGroup.options.map(
                                        (option, optionIndex) => (
                                            <div
                                                key={`size-${optionIndex}`}
                                                className="grid gap-3 sm:grid-cols-[1fr_8rem_auto]"
                                            >
                                                <FormField
                                                    label={
                                                        optionIndex === 0
                                                            ? 'Nombre'
                                                            : undefined
                                                    }
                                                    htmlFor={`size-name-${optionIndex}`}
                                                    error={resolveFieldError(
                                                        `option_groups.${sizeGroupIndex}.options.${optionIndex}.name`,
                                                        clientErrors,
                                                        errors,
                                                    )}
                                                >
                                                    <Input
                                                        id={`size-name-${optionIndex}`}
                                                        placeholder={
                                                            SECTION_CONFIG.size
                                                                .optionPlaceholder
                                                        }
                                                        value={option.name}
                                                        onChange={(event) => {
                                                            updateSizeOption(
                                                                optionIndex,
                                                                {
                                                                    name: event
                                                                        .target
                                                                        .value,
                                                                },
                                                            );
                                                            clearFieldError(
                                                                `option_groups.${sizeGroupIndex}.options.${optionIndex}.name`,
                                                            );
                                                        }}
                                                    />
                                                </FormField>
                                                <FormField
                                                    label={
                                                        optionIndex === 0
                                                            ? 'Precio (MXN)'
                                                            : undefined
                                                    }
                                                    htmlFor={`size-price-${optionIndex}`}
                                                    error={resolveFieldError(
                                                        `option_groups.${sizeGroupIndex}.options.${optionIndex}.price_modifier`,
                                                        clientErrors,
                                                        errors,
                                                    )}
                                                >
                                                    <Input
                                                        id={`size-price-${optionIndex}`}
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        placeholder="0.00"
                                                        value={
                                                            option.price_modifier
                                                        }
                                                        onChange={(event) => {
                                                            updateSizeOption(
                                                                optionIndex,
                                                                {
                                                                    price_modifier:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                },
                                                            );
                                                            clearFieldError(
                                                                `option_groups.${sizeGroupIndex}.options.${optionIndex}.price_modifier`,
                                                            );
                                                        }}
                                                    />
                                                </FormField>
                                                <div
                                                    className={
                                                        optionIndex === 0
                                                            ? 'flex items-end pb-0.5'
                                                            : 'flex items-center'
                                                    }
                                                >
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-muted-foreground hover:text-destructive"
                                                        aria-label="Quitar tamaño"
                                                        onClick={() =>
                                                            removeSizeOption(
                                                                optionIndex,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                        ),
                                    )}

                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={addSizeOption}
                                    >
                                        Agregar tamaño
                                    </Button>
                                </div>
                            ) : null}
                        </div>

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

                    <ProductOptionGroupsFields
                        groups={groups}
                        onChange={setGroups}
                        clientErrors={clientErrors}
                        serverErrors={errors}
                        onClearError={clearFieldError}
                    />

                    <PromotionItemDialog
                        open={itemDialogOpen}
                        onOpenChange={setItemDialogOpen}
                        mode={editingIndex === null ? 'create' : 'edit'}
                        item={draftItem}
                        onItemChange={setDraftItem}
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
