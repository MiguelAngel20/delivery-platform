import { useEffect, useMemo, useState } from 'react';
import { buildInitialSelectionFromCartLine } from '@/apps/storefront/cart/cart-line-to-selection';
import type { CartLine } from '@/apps/storefront/cart/use-storefront-cart';
import {
    addonQuantityForOption,
    buildInitialOptionSelection,
    buildSelectedProductOptions,
    groupSelectionCount,
    isGroupSelectionValid,
    isSingleChoiceGroup,
    selectionHint,
} from '@/apps/storefront/components/product-option-selection';
import { formatMoney } from '@/apps/storefront/mocks';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export type StorefrontProductOption = {
    id: number;
    name: string;
    description?: string | null;
    price_modifier: number;
    is_default: boolean;
    option_cluster_id?: number | null;
};

export type StorefrontOptionCluster = {
    id: number;
    name: string;
    sort_order?: number;
    options: StorefrontProductOption[];
};

export type StorefrontOptionGroup = {
    id: number;
    name: string;
    type: 'removable' | 'addon' | 'choice' | 'size' | string;
    is_required: boolean;
    min_selection: number;
    max_selection: number;
    has_option_clusters?: boolean;
    clusters?: StorefrontOptionCluster[];
    options: StorefrontProductOption[];
};

export type StorefrontProduct = {
    id: number | string;
    name: string;
    description: string;
    price: number;
    has_size_options?: boolean;
    allow_special_instructions?: boolean;
    option_groups?: StorefrontOptionGroup[];
    // legacy fallbacks
    ingredients?: string[];
    extras?: Array<{ id: string; name: string; price: number }>;
};

export type SelectedProductOption = {
    option_id: number;
    group_id: number;
    name: string;
    action: 'selected' | 'removed' | 'added';
    price_modifier: number;
    quantity?: number;
};

type ProductDialogProps = {
    product: StorefrontProduct | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editLine?: CartLine | null;
    confirmLabel?: string;
    onConfirm: (payload: {
        quantity: number;
        note?: string;
        selectedOptions: SelectedProductOption[];
        // legacy cart compatibility
        extras: Array<{ id: string; name: string; price: number }>;
        removedIngredients: string[];
    }) => void;
};

export function ProductDialog({
    product,
    open,
    onOpenChange,
    editLine = null,
    confirmLabel,
    onConfirm,
}: ProductDialogProps) {
    const [quantity, setQuantity] = useState(1);
    const [note, setNote] = useState('');
    const [selectedByGroup, setSelectedByGroup] = useState<
        Record<number, number[]>
    >({});
    const [optionQuantities, setOptionQuantities] = useState<
        Record<number, number>
    >({});

    const groups = product?.option_groups ?? [];

    useEffect(() => {
        if (!product || !open) {
            return;
        }

        if (editLine) {
            const { selectedByGroup: initialSelection, optionQuantities: initialQuantities } =
                buildInitialSelectionFromCartLine(product, editLine);
            setSelectedByGroup(initialSelection);
            setOptionQuantities(initialQuantities);
            setQuantity(editLine.quantity);
            setNote(editLine.note ?? '');

            return;
        }

        setSelectedByGroup(
            buildInitialOptionSelection(product.option_groups ?? []),
        );
        setOptionQuantities({});
        setQuantity(1);
        setNote('');
    }, [product, open, editLine]);

    const unitTotal = useMemo(() => {
        if (!product) {
            return 0;
        }

        let total = product.price;

        for (const group of groups) {
            const selectedIds = selectedByGroup[group.id] ?? [];

            for (const option of group.options) {
                const selected = selectedIds.includes(option.id);

                if (
                    (group.type === 'addon' ||
                        group.type === 'choice' ||
                        group.type === 'size') &&
                    selected
                ) {
                    const qty =
                        group.type === 'addon'
                            ? addonQuantityForOption(
                                  option.id,
                                  selectedIds,
                                  optionQuantities,
                              )
                            : 1;
                    total += option.price_modifier * qty;
                }
            }
        }

        return total;
    }, [product, groups, selectedByGroup, optionQuantities]);

    const selectionValid = useMemo(() => {
        if (!product) {
            return false;
        }

        return groups.every((group) => {
            const selectedIds = selectedByGroup[group.id] ?? [];
            const selectedCount = groupSelectionCount(
                group,
                selectedIds,
                optionQuantities,
            );

            return isGroupSelectionValid(group, selectedCount);
        });
    }, [product, groups, selectedByGroup, optionQuantities]);

    if (!product) {
        return null;
    }

    const setAddonQuantity = (
        group: StorefrontOptionGroup,
        optionId: number,
        nextQuantity: number,
    ) => {
        const selected = new Set(selectedByGroup[group.id] ?? []);
        const currentQty = addonQuantityForOption(
            optionId,
            [...selected],
            optionQuantities,
        );
        const usedWithoutThis =
            groupSelectionCount(group, [...selected], optionQuantities) -
            currentQty;

        if (nextQuantity <= 0) {
            selected.delete(optionId);
            const nextQuantities = { ...optionQuantities };
            delete nextQuantities[optionId];
            setSelectedByGroup({
                ...selectedByGroup,
                [group.id]: [...selected],
            });
            setOptionQuantities(nextQuantities);

            return;
        }

        if (usedWithoutThis + nextQuantity > group.max_selection) {
            return;
        }

        selected.add(optionId);
        setSelectedByGroup({
            ...selectedByGroup,
            [group.id]: [...selected],
        });
        setOptionQuantities({
            ...optionQuantities,
            [optionId]: nextQuantity,
        });
    };

    const toggleOption = (group: StorefrontOptionGroup, optionId: number) => {
        if (group.type === 'addon') {
            const selected = selectedByGroup[group.id] ?? [];
            const currentQty = addonQuantityForOption(
                optionId,
                selected,
                optionQuantities,
            );

            setAddonQuantity(group, optionId, currentQty > 0 ? 0 : 1);

            return;
        }

        setSelectedByGroup((current) => {
            const selected = new Set(current[group.id] ?? []);

            if (isSingleChoiceGroup(group)) {
                return { ...current, [group.id]: [optionId] };
            }

            if (selected.has(optionId)) {
                selected.delete(optionId);
            } else {
                if (selected.size >= group.max_selection) {
                    return current;
                }

                selected.add(optionId);
            }

            return { ...current, [group.id]: [...selected] };
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {editLine ? 'Editar' : ''} {product.name}
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-5">
                    <p className="text-sm text-muted-foreground">
                        {product.description}
                    </p>

                    {groups.map((group) => {
                        const selectedIds = selectedByGroup[group.id] ?? [];
                        const selectedCount = groupSelectionCount(
                            group,
                            selectedIds,
                            optionQuantities,
                        );
                        const atMax = selectedCount >= group.max_selection;
                        const hint = selectionHint(group);
                        const groupValid = isGroupSelectionValid(
                            group,
                            selectedCount,
                        );
                        const showAbsolutePrice = group.type === 'size';
                        const showClusters =
                            Boolean(group.has_option_clusters) &&
                            (group.clusters?.length ?? 0) > 0;

                        const renderOption = (
                            option: StorefrontProductOption,
                        ) => {
                            const selected = selectedIds.includes(option.id);
                            const optionDisabled =
                                !selected &&
                                atMax &&
                                group.type !== 'removable';
                            const absolutePrice =
                                product.price + option.price_modifier;

                            if (isSingleChoiceGroup(group)) {
                                return (
                                    <li
                                        key={option.id}
                                        className="flex items-center justify-between gap-3"
                                    >
                                        <label className="flex items-center gap-2 text-sm">
                                            <input
                                                type="radio"
                                                name={`group-${group.id}`}
                                                checked={selected}
                                                onChange={() =>
                                                    toggleOption(
                                                        group,
                                                        option.id,
                                                    )
                                                }
                                            />
                                            {option.name}
                                        </label>
                                        {showAbsolutePrice ? (
                                            <span className="text-sm font-medium text-navy">
                                                {formatMoney(absolutePrice)}
                                            </span>
                                        ) : option.price_modifier !== 0 ? (
                                            <span className="text-sm text-muted-foreground">
                                                +
                                                {formatMoney(
                                                    option.price_modifier,
                                                )}
                                            </span>
                                        ) : null}
                                    </li>
                                );
                            }

                            if (group.type === 'addon') {
                                const addonQty = addonQuantityForOption(
                                    option.id,
                                    selectedIds,
                                    optionQuantities,
                                );
                                const canIncrease =
                                    selectedCount < group.max_selection;

                                return (
                                    <li
                                        key={option.id}
                                        className="flex items-center justify-between gap-3"
                                    >
                                        <div className="flex min-w-0 flex-1 items-center gap-2">
                                            {addonQty > 0 ? (
                                                <div className="flex items-center gap-1.5">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        className="size-8 p-0"
                                                        aria-label={`Quitar ${option.name}`}
                                                        onClick={() =>
                                                            setAddonQuantity(
                                                                group,
                                                                option.id,
                                                                addonQty - 1,
                                                            )
                                                        }
                                                    >
                                                        -
                                                    </Button>
                                                    <span className="w-5 text-center text-sm font-semibold">
                                                        {addonQty}
                                                    </span>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        className="size-8 p-0"
                                                        aria-label={`Agregar ${option.name}`}
                                                        disabled={!canIncrease}
                                                        onClick={() =>
                                                            setAddonQuantity(
                                                                group,
                                                                option.id,
                                                                addonQty + 1,
                                                            )
                                                        }
                                                    >
                                                        +
                                                    </Button>
                                                </div>
                                            ) : (
                                                <Checkbox
                                                    checked={false}
                                                    disabled={optionDisabled}
                                                    onCheckedChange={() =>
                                                        toggleOption(
                                                            group,
                                                            option.id,
                                                        )
                                                    }
                                                />
                                            )}
                                            <Label
                                                className={`min-w-0 font-normal ${optionDisabled ? 'text-muted-foreground' : ''}`}
                                            >
                                                {option.name}
                                            </Label>
                                        </div>
                                        {option.price_modifier !== 0 ? (
                                            <span className="shrink-0 text-sm text-muted-foreground">
                                                +
                                                {formatMoney(
                                                    option.price_modifier *
                                                        Math.max(1, addonQty),
                                                )}
                                            </span>
                                        ) : null}
                                    </li>
                                );
                            }

                            return (
                                <li
                                    key={option.id}
                                    className="flex items-center justify-between gap-3"
                                >
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            checked={selected}
                                            disabled={optionDisabled}
                                            onCheckedChange={() =>
                                                toggleOption(group, option.id)
                                            }
                                        />
                                        <Label
                                            className={`font-normal ${optionDisabled ? 'text-muted-foreground' : ''}`}
                                        >
                                            {option.name}
                                        </Label>
                                    </div>
                                </li>
                            );
                        };

                        return (
                            <div key={group.id} className="space-y-2">
                                <div>
                                    <p className="text-sm font-medium text-navy">
                                        {group.name}
                                        {group.is_required ? ' *' : ''}
                                    </p>
                                    {hint ? (
                                        <p
                                            className={`text-xs ${groupValid ? 'text-muted-foreground' : 'text-destructive'}`}
                                        >
                                            {hint}
                                            {group.max_selection > 1
                                                ? ` · ${selectedCount} de ${group.max_selection}`
                                                : ''}
                                        </p>
                                    ) : null}
                                </div>

                                {showClusters ? (
                                    <div className="space-y-4">
                                        {group.clusters!.map((cluster) => (
                                            <div
                                                key={cluster.id}
                                                className="space-y-2"
                                            >
                                                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                                    {cluster.name}
                                                </p>
                                                <ul className="space-y-2">
                                                    {cluster.options.map(
                                                        (option) =>
                                                            renderOption(
                                                                option,
                                                            ),
                                                    )}
                                                </ul>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <ul className="space-y-2">
                                        {group.options.map((option) =>
                                            renderOption(option),
                                        )}
                                    </ul>
                                )}
                            </div>
                        );
                    })}

                    {product.allow_special_instructions !== false ? (
                        <div className="space-y-2">
                            <Label htmlFor="product-note">Notas</Label>
                            <Textarea
                                id="product-note"
                                value={note}
                                onChange={(event) =>
                                    setNote(event.target.value)
                                }
                                placeholder="Ej. Bien cocida"
                                rows={3}
                            />
                            <p className="text-xs text-muted-foreground">
                                Solo se pueden modificar las opciones
                                disponibles.
                            </p>
                        </div>
                    ) : null}

                    <div className="flex items-center justify-between gap-3">
                        <p className="text-sm font-medium text-navy">
                            Cantidad
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
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        className="min-h-12 w-full"
                        disabled={!selectionValid}
                        onClick={() => {
                            const selectedOptions = buildSelectedProductOptions(
                                groups,
                                selectedByGroup,
                                optionQuantities,
                            );
                            const extras = selectedOptions
                                .filter(
                                    (option) =>
                                        option.action === 'added' ||
                                        option.action === 'selected',
                                )
                                .filter((option) => option.price_modifier !== 0)
                                .map((option) => ({
                                    id: String(option.option_id),
                                    name: option.name,
                                    price:
                                        option.price_modifier *
                                        Math.max(1, option.quantity ?? 1),
                                }));
                            const removedIngredients = selectedOptions
                                .filter((option) => option.action === 'removed')
                                .map((option) => option.name);

                            onConfirm({
                                quantity,
                                note: note.trim() || undefined,
                                selectedOptions,
                                extras,
                                removedIngredients,
                            });
                            onOpenChange(false);
                        }}
                    >
                        {confirmLabel ?? 'Agregar al carrito'} ·{' '}
                        {formatMoney(unitTotal * quantity)}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
