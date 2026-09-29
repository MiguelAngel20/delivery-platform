import { Pencil, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
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
import { Textarea } from '@/components/ui/textarea';
import { validatePromotionItemDraft } from '@/lib/catalog/validate-promotion-form';

type PromotionItemDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'create' | 'edit';
    item: PromotionItemDraft;
    onItemChange: (item: PromotionItemDraft) => void;
    onSave: () => void;
};

function clonePromotionItem(item: PromotionItemDraft): PromotionItemDraft {
    return structuredClone(item);
}

export function createEmptyPromotionItem(): PromotionItemDraft {
    return {
        is_external_item: true,
        name: '',
        description: '',
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
    onSave,
}: PromotionItemDialogProps) {
    const [modalErrors, setModalErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (!open) {
            setModalErrors({});
        }
    }, [open]);

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
                        Describe lo que incluye la promoción. El cliente solo
                        ve esta lista; la personalización va en la promoción.
                    </DialogDescription>
                </DialogHeader>

                <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-4">
                    <FormField label="Nombre" error={modalErrors.name}>
                        <Input
                            value={item.name}
                            onChange={(event) => {
                                onItemChange({
                                    ...item,
                                    is_external_item: true,
                                    name: event.target.value,
                                });
                                clearModalError('name');
                            }}
                            placeholder="Ej. 250g Carne de res"
                        />
                    </FormField>
                    <FormField
                        label="Descripción"
                        error={modalErrors.description}
                    >
                        <Textarea
                            value={item.description ?? ''}
                            rows={2}
                            onChange={(event) => {
                                onItemChange({
                                    ...item,
                                    is_external_item: true,
                                    description: event.target.value,
                                });
                            }}
                            placeholder="Opcional. Se muestra si la escribes."
                        />
                    </FormField>
                    <FormField label="Cantidad" error={modalErrors.quantity}>
                        <Input
                            type="number"
                            min="0.01"
                            step="0.01"
                            value={item.quantity}
                            onChange={(event) => {
                                onItemChange({
                                    ...item,
                                    is_external_item: true,
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
    const quantity = item.quantity.trim();
    const parts = [quantity !== '' && quantity !== '1' ? `${quantity} × ${item.name}` : item.name];

    if (item.description?.trim()) {
        parts.push(item.description.trim());
    }

    return parts.filter(Boolean).join(' · ');
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
                        Lo que incluye
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {items.length === 0
                            ? 'Opcional. Lista lo que lleva la promoción para que el cliente lo vea. Ejemplo: 250g Carne de res.'
                            : `${items.length} incluido${items.length === 1 ? '' : 's'}. Solo se muestran como descripción.`}
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
                    Sin ítems por ahora. Puedes guardar la promoción solo con
                    nombre, descripción y precio.
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
