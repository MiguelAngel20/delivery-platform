import { useEffect, useMemo, useState } from 'react';
import { ProductOptionGroupsSelector } from '@/apps/storefront/components/product-option-groups-selector';
import {
    buildInitialOptionSelection,
    buildSelectedProductOptions,
    isOptionSelectionValid,
    itemToCustomizerProduct,
} from '@/apps/storefront/components/product-option-selection';
import type { PromotionCartItemSelection } from '@/apps/storefront/cart/use-storefront-cart';
import { formatMoney } from '@/apps/storefront/mocks';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export type StorefrontPromotionItem = {
    id: number;
    name: string;
    quantity: number;
    is_external_item: boolean;
    product_id?: number | null;
    allow_special_instructions?: boolean;
    option_groups?: Array<{
        id: number;
        name: string;
        type: string;
        is_required: boolean;
        min_selection: number;
        max_selection: number;
        options: Array<{
            id: number;
            name: string;
            description?: string | null;
            price_modifier: number;
            is_default: boolean;
        }>;
    }>;
};

export type StorefrontPromotion = {
    id: number;
    name: string;
    description: string;
    price: number;
    composition: string;
    image_url?: string | null;
    items: StorefrontPromotionItem[];
};

type PromotionDialogProps = {
    promotionId: number | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editSelections?: PromotionCartItemSelection[];
    editQuantity?: number;
    confirmLabel?: string;
    onConfirm: (payload: {
        promotion: StorefrontPromotion;
        quantity: number;
        promotionItems: PromotionCartItemSelection[];
        note?: string;
    }) => void;
};

type ItemDraft = {
    selectedByGroup: Record<number, number[]>;
    note: string;
};

function summarizeItemSelections(
    item: StorefrontPromotionItem,
    draft: ItemDraft | undefined,
): { variants: string[]; extras: string[]; removed: string[] } {
    const groups = item.option_groups ?? [];
    const selectedOptions = buildSelectedProductOptions(
        groups,
        draft?.selectedByGroup ?? {},
    );

    const variants: string[] = [];
    const extras: string[] = [];
    const removed: string[] = [];

    for (const option of selectedOptions) {
        if (option.action === 'selected') {
            variants.push(option.name);
        }

        if (option.action === 'added') {
            extras.push(option.name);
        }

        if (option.action === 'removed') {
            removed.push(option.name);
        }
    }

    return { variants, extras, removed };
}

function formatItemConfigurationLines(
    draft: ItemDraft | undefined,
    summary: ReturnType<typeof summarizeItemSelections>,
): string[] {
    const lines: string[] = [];

    if (summary.variants.length > 0) {
        lines.push(summary.variants.join(' · '));
    }

    if (summary.extras.length > 0) {
        lines.push(`Extras: ${summary.extras.join(', ')}`);
    }

    if (summary.removed.length > 0) {
        lines.push(`Sin: ${summary.removed.join(', ')}`);
    }

    if (draft?.note.trim()) {
        lines.push(`Nota: ${draft.note.trim()}`);
    }

    return lines;
}

export function PromotionDialog({
    promotionId,
    open,
    onOpenChange,
    editSelections,
    editQuantity,
    confirmLabel,
    onConfirm,
}: PromotionDialogProps) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [promotion, setPromotion] = useState<StorefrontPromotion | null>(null);
    const [quantity, setQuantity] = useState(1);
    const [stepIndex, setStepIndex] = useState(0);
    const [itemDrafts, setItemDrafts] = useState<Record<number, ItemDraft>>({});

    const items = promotion?.items ?? [];
    const confirmStepIndex = items.length;
    const isConfirmStep = stepIndex === confirmStepIndex;
    const currentItem = isConfirmStep ? null : items[stepIndex];

    useEffect(() => {
        if (!open || promotionId === null) {
            return;
        }

        setLoading(true);
        setError(null);
        setStepIndex(0);
        setQuantity(editQuantity ?? 1);

        void fetch(`/cart/promotions/${promotionId}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error('Promoción no disponible');
                }

                const data = (await response.json()) as {
                    promotion: StorefrontPromotion;
                };

                const drafts: Record<number, ItemDraft> = {};

                for (const item of data.promotion.items) {
                    const existing = editSelections?.find(
                        (selection) =>
                            selection.promotionItemId === item.id,
                    );
                    const groups = item.option_groups ?? [];
                    const initial = buildInitialOptionSelection(groups);

                    if (existing?.selectedOptions?.length) {
                        for (const group of groups) {
                            initial[group.id] = group.options
                                .filter((option) =>
                                    existing.selectedOptions.some(
                                        (selected) => {
                                            if (selected.option_id !== option.id) {
                                                return false;
                                            }

                                            if (group.type === 'removable') {
                                                return (
                                                    selected.action ===
                                                    'removed'
                                                );
                                            }

                                            return (
                                                selected.action === 'added' ||
                                                selected.action === 'selected'
                                            );
                                        },
                                    ),
                                )
                                .map((option) => option.id);
                        }
                    }

                    drafts[item.id] = {
                        selectedByGroup: initial,
                        note: existing?.note ?? '',
                    };
                }

                setPromotion(data.promotion);
                setItemDrafts(drafts);
            })
            .catch(() => {
                setError('No se pudo cargar la promoción.');
                setPromotion(null);
            })
            .finally(() => {
                setLoading(false);
            });
    }, [open, promotionId, editQuantity, editSelections]);

    const currentDraft = currentItem ? itemDrafts[currentItem.id] : null;
    const currentGroups = currentItem?.option_groups ?? [];
    const currentItemValid =
        !currentItem ||
        !currentDraft ||
        isOptionSelectionValid(currentGroups, currentDraft.selectedByGroup);

    const builtItems = useMemo(() => {
        if (!promotion) {
            return [];
        }

        return promotion.items.map((item) => {
            const draft = itemDrafts[item.id];
            const groups = item.option_groups ?? [];

            return {
                promotionItemId: item.id,
                name: item.name,
                selectedOptions: buildSelectedProductOptions(
                    groups,
                    draft?.selectedByGroup ?? {},
                ),
                note: draft?.note.trim() || undefined,
            };
        });
    }, [promotion, itemDrafts]);

    function handleConfirm() {
        if (!promotion) {
            return;
        }

        onConfirm({
            promotion,
            quantity,
            promotionItems: builtItems,
        });
        onOpenChange(false);
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{promotion?.name ?? 'Promoción'}</DialogTitle>
                </DialogHeader>

                {loading ? (
                    <p className="text-sm text-muted-foreground">
                        Cargando promoción…
                    </p>
                ) : null}

                {error ? (
                    <p className="text-sm text-destructive">{error}</p>
                ) : null}

                {promotion && !loading && !error ? (
                    <div className="space-y-5">
                        {isConfirmStep ? (
                            <>
                                <div className="space-y-3 rounded-xl border border-border bg-muted/20 p-3">
                                    <p className="text-sm font-medium text-navy">
                                        Productos
                                    </p>
                                    {promotion.items.map((item) => {
                                        const draft = itemDrafts[item.id];
                                        const summary = summarizeItemSelections(
                                            item,
                                            draft,
                                        );
                                        const configLines =
                                            formatItemConfigurationLines(
                                                draft,
                                                summary,
                                            );

                                        return (
                                            <div
                                                key={item.id}
                                                className="space-y-0.5 border-b border-border pb-3 last:border-0 last:pb-0"
                                            >
                                                <p className="text-sm font-medium text-foreground">
                                                    {item.name}
                                                </p>
                                                {configLines.length > 0 ? (
                                                    configLines.map((line) => (
                                                        <p
                                                            key={line}
                                                            className="text-xs text-muted-foreground"
                                                        >
                                                            {line}
                                                        </p>
                                                    ))
                                                ) : (
                                                    <p className="text-xs text-muted-foreground">
                                                        Sin personalización
                                                    </p>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>

                                <div className="flex items-center justify-between gap-3">
                                    <p className="text-sm font-medium text-navy">
                                        Cantidad de promociones
                                    </p>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="min-h-11 min-w-11"
                                            onClick={() =>
                                                setQuantity((value) =>
                                                    Math.max(1, value - 1),
                                                )
                                            }
                                        >
                                            -
                                        </Button>
                                        <span className="w-8 text-center font-semibold">
                                            {quantity}
                                        </span>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="min-h-11 min-w-11"
                                            onClick={() =>
                                                setQuantity((value) => value + 1)
                                            }
                                        >
                                            +
                                        </Button>
                                    </div>
                                </div>
                            </>
                        ) : currentItem && currentDraft ? (
                            <>
                                <div>
                                    <p className="text-sm font-medium text-navy">
                                        {currentItem.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Producto {stepIndex + 1} de {items.length}
                                    </p>
                                </div>

                                {(currentItem.option_groups?.length ?? 0) >
                                0 ? (
                                    <ProductOptionGroupsSelector
                                        groups={
                                            itemToCustomizerProduct(currentItem)
                                                .option_groups ?? []
                                        }
                                        selectedByGroup={
                                            currentDraft.selectedByGroup
                                        }
                                        onChange={(selectedByGroup) =>
                                            setItemDrafts((current) => ({
                                                ...current,
                                                [currentItem.id]: {
                                                    ...current[currentItem.id],
                                                    selectedByGroup,
                                                },
                                            }))
                                        }
                                    />
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Este producto no requiere personalización
                                        adicional.
                                    </p>
                                )}

                                <div className="space-y-2">
                                    <Label htmlFor={`item-note-${currentItem.id}`}>
                                        Notas para este producto
                                    </Label>
                                    <Textarea
                                        id={`item-note-${currentItem.id}`}
                                        value={currentDraft.note}
                                        onChange={(event) =>
                                            setItemDrafts((current) => ({
                                                ...current,
                                                [currentItem.id]: {
                                                    ...current[currentItem.id],
                                                    note: event.target.value,
                                                },
                                            }))
                                        }
                                        rows={2}
                                        placeholder="Ej. Bien cocida, sin hielo…"
                                    />
                                </div>
                            </>
                        ) : null}
                    </div>
                ) : null}

                <DialogFooter className="gap-3 sm:justify-stretch">
                    {promotion && !loading && !error ? (
                        <>
                            {stepIndex > 0 ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="min-h-12 flex-1"
                                    onClick={() =>
                                        setStepIndex((value) => value - 1)
                                    }
                                >
                                    Atrás
                                </Button>
                            ) : null}
                            {!isConfirmStep ? (
                                <Button
                                    type="button"
                                    className="min-h-12 flex-1"
                                    disabled={!currentItemValid}
                                    onClick={() =>
                                        setStepIndex((value) => value + 1)
                                    }
                                >
                                    {stepIndex === items.length - 1
                                        ? 'Continuar'
                                        : 'Siguiente producto'}
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    className="min-h-12 flex-1"
                                    onClick={handleConfirm}
                                >
                                    {confirmLabel ?? 'Agregar al carrito'} ·{' '}
                                    {formatMoney(
                                        (promotion?.price ?? 0) * quantity,
                                    )}
                                </Button>
                            )}
                        </>
                    ) : null}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
