import { Form } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import { FormField } from '@/components/forms/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    resolveFieldError,
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

const emptyItem = (): PromotionItemDraft => ({
    is_external_item: false,
    product_id: '',
    name: '',
    quantity: '1',
});

export function PromotionForm({
    options,
    promotion,
    action,
    submitLabel,
    cancelSlot,
}: PromotionFormProps) {
    const [branchId, setBranchId] = useState(promotion?.branch_id ?? '');
    const [items, setItems] = useState<PromotionItemDraft[]>(
        promotion?.items?.length ? promotion.items : [emptyItem()],
    );
    const [clientErrors, setClientErrors] = useState<PromotionFormClientErrors>(
        {},
    );
    const itemsInputRef = useRef<HTMLInputElement>(null);
    const formRef = useRef<{ getData: () => Record<string, unknown> } | null>(
        null,
    );

    useEffect(() => {
        setClientErrors({});
    }, [promotion?.id]);

    const products = useMemo(
        () =>
            options.products.filter(
                (product) => String(product.branch_id) === branchId,
            ),
        [options.products, branchId],
    );

    function clearFieldError(key: string) {
        setClientErrors((current) => {
            const next = { ...current };
            delete next[key];

            return next;
        });
    }

    function clearItemFieldError(index: number, field: string) {
        setClientErrors((current) => {
            const next = { ...current };
            delete next[`items.${index}.${field}`];
            delete next.items;

            return next;
        });
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
            itemsInputRef.current.value = JSON.stringify(items);
        }

        return true;
    }

    const updateItem = (index: number, patch: Partial<PromotionItemDraft>) => {
        setItems((current) =>
            current.map((item, itemIndex) =>
                itemIndex === index ? { ...item, ...patch } : item,
            ),
        );
    };

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

                    <section className="space-y-4 rounded-xl border border-border bg-surface p-4">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h2 className="text-base font-semibold text-foreground">
                                    Ítems
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Producto del menú o elemento externo.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setItems((current) => [...current, emptyItem()]);
                                    clearFieldError('items');
                                }}
                            >
                                + Ítem
                            </Button>
                        </div>

                        {items.map((item, index) => (
                            <div
                                key={index}
                                className="space-y-3 rounded-lg border border-border p-3"
                            >
                                <div className="flex flex-wrap gap-4 text-sm text-foreground">
                                    <label className="flex items-center gap-2">
                                        <input
                                            type="radio"
                                            checked={!item.is_external_item}
                                            onChange={() => {
                                                updateItem(index, {
                                                    is_external_item: false,
                                                    name: '',
                                                });
                                                clearItemFieldError(index, 'name');
                                                clearItemFieldError(
                                                    index,
                                                    'product_id',
                                                );
                                            }}
                                        />
                                        Producto del menú
                                    </label>
                                    <label className="flex items-center gap-2">
                                        <input
                                            type="radio"
                                            checked={item.is_external_item}
                                            onChange={() => {
                                                updateItem(index, {
                                                    is_external_item: true,
                                                    product_id: null,
                                                });
                                                clearItemFieldError(index, 'name');
                                                clearItemFieldError(
                                                    index,
                                                    'product_id',
                                                );
                                            }}
                                        />
                                        Elemento externo
                                    </label>
                                </div>

                                {item.is_external_item ? (
                                    <FormField
                                        label="Nombre del ítem"
                                        error={resolveFieldError(
                                            `items.${index}.name`,
                                            clientErrors,
                                            errors,
                                        )}
                                    >
                                        <Input
                                            value={item.name}
                                            onChange={(event) => {
                                                updateItem(index, {
                                                    name: event.target.value,
                                                });
                                                clearItemFieldError(index, 'name');
                                            }}
                                            placeholder="Ej. Jugo"
                                        />
                                    </FormField>
                                ) : (
                                    <FormField
                                        label="Producto"
                                        error={resolveFieldError(
                                            `items.${index}.product_id`,
                                            clientErrors,
                                            errors,
                                        )}
                                    >
                                        <select
                                            value={item.product_id ?? ''}
                                            onChange={(event) => {
                                                const selected = products.find(
                                                    (product) =>
                                                        product.value ===
                                                        event.target.value,
                                                );
                                                updateItem(index, {
                                                    product_id: event.target.value,
                                                    name: selected?.label ?? '',
                                                });
                                                clearItemFieldError(
                                                    index,
                                                    'product_id',
                                                );
                                            }}
                                            className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                        >
                                            <option value="">Selecciona producto</option>
                                            {products.map((product) => (
                                                <option
                                                    key={product.value}
                                                    value={product.value}
                                                >
                                                    {product.label}
                                                </option>
                                            ))}
                                        </select>
                                    </FormField>
                                )}

                                <FormField
                                    label="Cantidad"
                                    error={resolveFieldError(
                                        `items.${index}.quantity`,
                                        clientErrors,
                                        errors,
                                    )}
                                >
                                    <Input
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        value={item.quantity}
                                        onChange={(event) => {
                                            updateItem(index, {
                                                quantity: event.target.value,
                                            });
                                            clearItemFieldError(index, 'quantity');
                                        }}
                                    />
                                </FormField>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="text-danger"
                                    onClick={() => {
                                        setItems((current) =>
                                            current.filter((_, i) => i !== index),
                                        );
                                        setClientErrors((current) => {
                                            const next: PromotionFormClientErrors =
                                                {};

                                            Object.entries(current).forEach(
                                                ([key, value]) => {
                                                    if (
                                                        key === 'items' ||
                                                        !key.startsWith('items.')
                                                    ) {
                                                        next[key] = value;

                                                        return;
                                                    }

                                                    const match =
                                                        /^items\.(\d+)\./.exec(
                                                            key,
                                                        );

                                                    if (match === null) {
                                                        next[key] = value;

                                                        return;
                                                    }

                                                    const itemIndex = Number(
                                                        match[1],
                                                    );

                                                    if (itemIndex < index) {
                                                        next[key] = value;
                                                    } else if (itemIndex > index) {
                                                        const newKey = key.replace(
                                                            `items.${itemIndex}.`,
                                                            `items.${itemIndex - 1}.`,
                                                        );
                                                        next[newKey] = value;
                                                    }
                                                },
                                            );

                                            return next;
                                        });
                                    }}
                                >
                                    Quitar ítem
                                </Button>
                            </div>
                        ))}
                        {resolveFieldError('items', clientErrors, errors) ? (
                            <p className="text-sm text-destructive">
                                {resolveFieldError('items', clientErrors, errors)}
                            </p>
                        ) : null}
                    </section>

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
