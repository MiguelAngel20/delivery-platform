import { Form } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import { FormField } from '@/components/forms/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

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

    const products = useMemo(
        () =>
            options.products.filter(
                (product) => String(product.branch_id) === branchId,
            ),
        [options.products, branchId],
    );

    const updateItem = (index: number, patch: Partial<PromotionItemDraft>) => {
        setItems((current) =>
            current.map((item, itemIndex) =>
                itemIndex === index ? { ...item, ...patch } : item,
            ),
        );
    };

    return (
        <Form
            action={action.url}
            method={action.method}
            encType="multipart/form-data"
            className="space-y-6"
        >
            {({ processing, errors }) => (
                <>
                    <input type="hidden" name="items" value={JSON.stringify(items)} />

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
                                disabled={Boolean(promotion?.id)}
                                value={branchId}
                                onChange={(event) => setBranchId(event.target.value)}
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
                            error={errors.status}
                        >
                            <select
                                id="status"
                                name="status"
                                required
                                defaultValue={promotion?.status ?? 'draft'}
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
                            error={errors.name}
                            className="md:col-span-2"
                        >
                            <Input
                                id="name"
                                name="name"
                                required
                                defaultValue={promotion?.name ?? ''}
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
                                defaultValue={promotion?.description ?? ''}
                            />
                        </FormField>

                        <FormField
                            label="Precio promocional (MXN)"
                            htmlFor="promotion_price"
                            required
                            error={errors.promotion_price}
                        >
                            <Input
                                id="promotion_price"
                                name="promotion_price"
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                defaultValue={promotion?.promotion_price ?? ''}
                            />
                        </FormField>

                        <FormField label="Imagen" htmlFor="image" error={errors.image}>
                            <Input id="image" name="image" type="file" accept="image/*" />
                        </FormField>

                        <FormField label="Inicio" htmlFor="starts_at" error={errors.starts_at}>
                            <Input
                                id="starts_at"
                                name="starts_at"
                                type="datetime-local"
                                defaultValue={
                                    promotion?.starts_at
                                        ? promotion.starts_at.slice(0, 16)
                                        : ''
                                }
                            />
                        </FormField>
                        <FormField label="Fin" htmlFor="ends_at" error={errors.ends_at}>
                            <Input
                                id="ends_at"
                                name="ends_at"
                                type="datetime-local"
                                defaultValue={
                                    promotion?.ends_at
                                        ? promotion.ends_at.slice(0, 16)
                                        : ''
                                }
                            />
                        </FormField>
                    </div>

                    <section className="space-y-4 rounded-xl border border-border bg-white p-4">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h2 className="text-base font-semibold text-navy">
                                    Ítems
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Producto del menú o elemento externo.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    setItems((current) => [...current, emptyItem()])
                                }
                            >
                                + Ítem
                            </Button>
                        </div>

                        {items.map((item, index) => (
                            <div
                                key={index}
                                className="space-y-3 rounded-lg border border-border p-3"
                            >
                                <div className="flex flex-wrap gap-4 text-sm">
                                    <label className="flex items-center gap-2">
                                        <input
                                            type="radio"
                                            checked={!item.is_external_item}
                                            onChange={() =>
                                                updateItem(index, {
                                                    is_external_item: false,
                                                    name: '',
                                                })
                                            }
                                        />
                                        Producto del menú
                                    </label>
                                    <label className="flex items-center gap-2">
                                        <input
                                            type="radio"
                                            checked={item.is_external_item}
                                            onChange={() =>
                                                updateItem(index, {
                                                    is_external_item: true,
                                                    product_id: null,
                                                })
                                            }
                                        />
                                        Elemento externo
                                    </label>
                                </div>

                                {item.is_external_item ? (
                                    <FormField label="Nombre del ítem">
                                        <Input
                                            value={item.name}
                                            onChange={(event) =>
                                                updateItem(index, {
                                                    name: event.target.value,
                                                })
                                            }
                                            placeholder="Ej. Jugo"
                                        />
                                    </FormField>
                                ) : (
                                    <FormField label="Producto">
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

                                <FormField label="Cantidad">
                                    <Input
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        value={item.quantity}
                                        onChange={(event) =>
                                            updateItem(index, {
                                                quantity: event.target.value,
                                            })
                                        }
                                    />
                                </FormField>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="text-danger"
                                    onClick={() =>
                                        setItems((current) =>
                                            current.filter((_, i) => i !== index),
                                        )
                                    }
                                >
                                    Quitar ítem
                                </Button>
                            </div>
                        ))}
                        {errors.items ? (
                            <p className="text-sm text-danger">{errors.items}</p>
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
