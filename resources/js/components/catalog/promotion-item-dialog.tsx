import { Pencil, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import {
    mapApiOptionGroupsToDrafts,
    type ProductOptionGroupApi,
    type ProductOptionGroupDraft,
} from '@/components/catalog/product-option-group-types';
import { ProductOptionGroupsFields } from '@/components/catalog/product-option-groups-fields';
import { ProductOptionGroupsReadonly } from '@/components/catalog/product-option-groups-readonly';
import type { PromotionItemDraft } from '@/components/catalog/promotion-form';
import { FormField } from '@/components/forms/form-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { validatePromotionItemDraft } from '@/lib/catalog/validate-promotion-form';
import { customization } from '@/routes/business/products';

type PromotionItemDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'create' | 'edit';
    item: PromotionItemDraft;
    onItemChange: (item: PromotionItemDraft) => void;
    products: CatalogFormOptions['products'];
    onSave: () => void;
};

function clonePromotionItem(item: PromotionItemDraft): PromotionItemDraft {
    return structuredClone(item);
}

export function createEmptyPromotionItem(): PromotionItemDraft {
    return {
        is_external_item: false,
        product_id: '',
        name: '',
        quantity: '1',
    };
}

export { clonePromotionItem };

export function PromotionItemDialog({
    open,
    onOpenChange,
    mode,
    item,
    onItemChange,
    products,
    onSave,
}: PromotionItemDialogProps) {
    const [modalErrors, setModalErrors] = useState<Record<string, string>>({});
    const [menuOptionGroups, setMenuOptionGroups] = useState<
        ProductOptionGroupDraft[]
    >([]);
    const [menuCustomizationLoading, setMenuCustomizationLoading] =
        useState(false);

    const loadMenuCustomization = useCallback(async (productId: string) => {
        if (productId.trim() === '') {
            setMenuOptionGroups([]);

            return;
        }

        setMenuCustomizationLoading(true);

        try {
            const response = await fetch(customization.url(Number(productId)), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('No se pudo cargar la personalización.');
            }

            const payload = (await response.json()) as {
                option_groups: ProductOptionGroupApi[];
            };

            setMenuOptionGroups(
                mapApiOptionGroupsToDrafts(payload.option_groups ?? []),
            );
        } catch {
            setMenuOptionGroups([]);
        } finally {
            setMenuCustomizationLoading(false);
        }
    }, []);

    useEffect(() => {
        if (!open) {
            setModalErrors({});

            return;
        }

        if (!item.is_external_item) {
            const productId = String(item.product_id ?? '').trim();
            void loadMenuCustomization(productId);
        } else {
            setMenuOptionGroups([]);
        }
    }, [open, item.is_external_item, item.product_id, loadMenuCustomization]);

    function clearModalError(key: string) {
        setModalErrors((current) => {
            const next = { ...current };
            delete next[key];

            return next;
        });
    }

    function handleSave() {
        const validationErrors = validatePromotionItemDraft(item);

        if (Object.keys(validationErrors).length > 0) {
            setModalErrors(validationErrors);

            return;
        }

        setModalErrors({});
        onSave();
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[90dvh] flex-col gap-0 overflow-hidden p-0 sm:max-w-2xl">
                <DialogHeader className="shrink-0 border-b border-border px-6 py-4 text-left">
                    <DialogTitle>
                        {mode === 'create' ? 'Agregar ítem' : 'Editar ítem'}
                    </DialogTitle>
                    <DialogDescription>
                        Producto del menú o elemento externo con su cantidad y
                        personalización.
                    </DialogDescription>
                </DialogHeader>

                <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-4">
                    <div className="flex flex-wrap gap-4 text-sm text-foreground">
                        <label className="flex items-center gap-2">
                            <input
                                type="radio"
                                checked={!item.is_external_item}
                                onChange={() => {
                                    onItemChange({
                                        ...item,
                                        is_external_item: false,
                                        name: '',
                                        option_groups: undefined,
                                    });
                                    clearModalError('name');
                                    clearModalError('product_id');
                                }}
                            />
                            Producto del menú
                        </label>
                        <label className="flex items-center gap-2">
                            <input
                                type="radio"
                                checked={item.is_external_item}
                                onChange={() => {
                                    onItemChange({
                                        ...item,
                                        is_external_item: true,
                                        product_id: null,
                                        option_groups: item.option_groups?.length
                                            ? item.option_groups
                                            : [],
                                    });
                                    clearModalError('name');
                                    clearModalError('product_id');
                                }}
                            />
                            Elemento externo
                        </label>
                    </div>

                    {item.is_external_item ? (
                        <>
                            <FormField
                                label="Nombre del ítem"
                                error={modalErrors.name}
                            >
                                <Input
                                    value={item.name}
                                    onChange={(event) => {
                                        onItemChange({
                                            ...item,
                                            name: event.target.value,
                                        });
                                        clearModalError('name');
                                    }}
                                    placeholder="Ej. Jugo"
                                />
                            </FormField>
                            <ProductOptionGroupsFields
                                groups={item.option_groups ?? []}
                                onChange={(groups) =>
                                    onItemChange({ ...item, option_groups: groups })
                                }
                                errorPrefix="option_groups"
                                clientErrors={modalErrors}
                                serverErrors={{}}
                                onClearError={clearModalError}
                                heading="Personalización del ítem"
                                description="Configura variantes, extras o ingredientes removibles para este elemento externo."
                            />
                        </>
                    ) : (
                        <>
                            <FormField
                                label="Producto"
                                error={modalErrors.product_id}
                            >
                                <select
                                    value={item.product_id ?? ''}
                                    onChange={(event) => {
                                        const selected = products.find(
                                            (product) =>
                                                product.value ===
                                                event.target.value,
                                        );
                                        onItemChange({
                                            ...item,
                                            product_id: event.target.value,
                                            name: selected?.label ?? '',
                                        });
                                        void loadMenuCustomization(
                                            event.target.value,
                                        );
                                        clearModalError('product_id');
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
                            {String(item.product_id ?? '').trim() !== '' ? (
                                <ProductOptionGroupsReadonly
                                    groups={menuOptionGroups}
                                    loading={menuCustomizationLoading}
                                />
                            ) : null}
                        </>
                    )}

                    <FormField label="Cantidad" error={modalErrors.quantity}>
                        <Input
                            type="number"
                            min="0.01"
                            step="0.01"
                            value={item.quantity}
                            onChange={(event) => {
                                onItemChange({
                                    ...item,
                                    quantity: event.target.value,
                                });
                                clearModalError('quantity');
                            }}
                        />
                    </FormField>
                </div>

                <DialogFooter className="shrink-0 border-t border-border px-6 py-4">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancelar
                    </Button>
                    <Button type="button" onClick={handleSave}>
                        {mode === 'create' ? 'Agregar' : 'Guardar'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

type PromotionItemListProps = {
    items: PromotionItemDraft[];
    clientErrors: Record<string, string>;
    serverErrors: Record<string, string>;
    onAdd: () => void;
    onEdit: (index: number) => void;
    onRemove: (index: number) => void;
    addDisabled?: boolean;
};

function itemSubtitle(item: PromotionItemDraft): string {
    const parts = [
        item.is_external_item ? 'Externo' : 'Menú',
        `Cantidad: ${item.quantity}`,
    ];

    if (item.is_external_item && (item.option_groups?.length ?? 0) > 0) {
        parts.push(
            `${item.option_groups?.length} sección(es) de personalización`,
        );
    }

    return parts.join(' · ');
}

export function PromotionItemList({
    items,
    clientErrors,
    serverErrors,
    onAdd,
    onEdit,
    onRemove,
    addDisabled = false,
}: PromotionItemListProps) {
    return (
        <section className="space-y-4 rounded-xl border border-border bg-surface p-4">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold text-foreground">
                        Ítems de la promoción
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {items.length === 0
                            ? 'Agrega productos del menú o elementos externos.'
                            : `${items.length} ítem${items.length === 1 ? '' : 's'} en la promoción.`}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="shrink-0"
                    disabled={addDisabled}
                    onClick={onAdd}
                >
                    Agregar ítem
                </Button>
            </div>

            {items.length === 0 ? (
                <div className="rounded-lg border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground">
                    Aún no hay ítems. Usa &quot;Agregar ítem&quot; para
                    comenzar.
                </div>
            ) : (
                <ul className="divide-y divide-border rounded-lg border border-border">
                    {items.map((item, index) => {
                        const itemError =
                            clientErrors[`items.${index}.name`] ??
                            clientErrors[`items.${index}.product_id`] ??
                            clientErrors[`items.${index}.quantity`] ??
                            serverErrors[`items.${index}.name`] ??
                            serverErrors[`items.${index}.product_id`] ??
                            serverErrors[`items.${index}.quantity`];

                        return (
                            <li
                                key={index}
                                className="flex items-center gap-3 px-3 py-3"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium text-foreground">
                                        {item.name.trim() !== ''
                                            ? item.name
                                            : 'Ítem sin nombre'}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {itemSubtitle(item)}
                                    </p>
                                    {itemError ? (
                                        <p className="mt-1 text-sm text-destructive">
                                            {itemError}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="flex shrink-0 items-center gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-9"
                                        aria-label={`Editar ${item.name || 'ítem'}`}
                                        onClick={() => onEdit(index)}
                                    >
                                        <Pencil className="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-9 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        aria-label={`Eliminar ${item.name || 'ítem'}`}
                                        onClick={() => onRemove(index)}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}

            {clientErrors.items ?? serverErrors.items ? (
                <p className="text-sm text-destructive">
                    {clientErrors.items ?? serverErrors.items}
                </p>
            ) : null}
        </section>
    );
}
