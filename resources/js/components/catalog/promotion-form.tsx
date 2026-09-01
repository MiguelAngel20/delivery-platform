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
import { FormField } from '@/components/forms/form-field';
import { Button } from '@/components/ui/button';
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
                            error={resolveFieldError('image', clientErrors, errors)}
                        >
                            <Input id="image" name="image" type="file" accept="image/*" />
                        </FormField>

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
                                        ? promotion.starts_at.slice(0, 16)
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
                                        ? promotion.ends_at.slice(0, 16)
                                        : ''
                                }
                                onChange={() => clearFieldError('ends_at')}
                                className="bg-background scheme-light dark:scheme-dark"
                            />
                        </FormField>
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
