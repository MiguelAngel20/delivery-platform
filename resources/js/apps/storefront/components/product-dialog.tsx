import { useEffect, useMemo, useState } from 'react';
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
};

export type StorefrontOptionGroup = {
    id: number;
    name: string;
    type: 'removable' | 'addon' | 'choice' | string;
    is_required: boolean;
    min_selection: number;
    max_selection: number;
    options: StorefrontProductOption[];
};

export type StorefrontProduct = {
    id: number | string;
    name: string;
    description: string;
    price: number;
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
};

type ProductDialogProps = {
    product: StorefrontProduct | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirm: (payload: {
        quantity: number;
        note?: string;
        selectedOptions: SelectedProductOption[];
        // legacy cart compatibility
        extras: Array<{ id: string; name: string; price: number }>;
        removedIngredients: string[];
    }) => void;
};

function isSingleChoiceGroup(group: StorefrontOptionGroup): boolean {
    return group.type === 'choice' && group.max_selection === 1;
}

function selectionHint(group: StorefrontOptionGroup): string | null {
    if (group.type === 'removable') {
        return null;
    }

    const { min_selection: min, max_selection: max } = group;

    if (min === max) {
        return min === 1 ? 'Elige 1 opción' : `Elige exactamente ${min} opciones`;
    }

    if (min === 0) {
        return max === 1
            ? 'Opcional · hasta 1 opción'
            : `Opcional · hasta ${max} opciones`;
    }

    return `Elige entre ${min} y ${max} opciones`;
}

function isGroupSelectionValid(
    group: StorefrontOptionGroup,
    selectedCount: number,
): boolean {
    if (group.type === 'removable') {
        return true;
    }

    if (group.type === 'choice' || group.is_required) {
        return (
            selectedCount >= group.min_selection &&
            selectedCount <= group.max_selection
        );
    }

    return selectedCount <= group.max_selection;
}

export function ProductDialog({
    product,
    open,
    onOpenChange,
    onConfirm,
}: ProductDialogProps) {
    const [quantity, setQuantity] = useState(1);
    const [note, setNote] = useState('');
    const [selectedByGroup, setSelectedByGroup] = useState<
        Record<number, number[]>
    >({});

    const groups = product?.option_groups ?? [];

    useEffect(() => {
        if (!product || !open) {
            return;
        }

        const initial: Record<number, number[]> = {};

        for (const group of product.option_groups ?? []) {
            if (group.type === 'removable') {
                initial[group.id] = group.options
                    .filter((option) => option.is_default)
                    .map((option) => option.id);
            } else if (group.type === 'choice') {
                const defaults = group.options.filter(
                    (option) => option.is_default,
                );

                if (defaults.length > 0) {
                    initial[group.id] = defaults
                        .slice(0, group.max_selection)
                        .map((option) => option.id);
                } else if (group.is_required && group.options.length > 0) {
                    initial[group.id] = group.options
                        .slice(0, group.min_selection)
                        .map((option) => option.id);
                } else {
                    initial[group.id] = [];
                }
            } else {
                initial[group.id] = [];
            }
        }

        setSelectedByGroup(initial);
        setQuantity(1);
        setNote('');
    }, [product, open]);

    const unitTotal = useMemo(() => {
        if (!product) {
            return 0;
        }

        let total = product.price;

        for (const group of groups) {
            const selectedIds = selectedByGroup[group.id] ?? [];

            for (const option of group.options) {
                const selected = selectedIds.includes(option.id);

                if (group.type === 'addon' && selected) {
                    total += option.price_modifier;
                }

                if (group.type === 'choice' && selected) {
                    total += option.price_modifier;
                }
            }
        }

        return total;
    }, [product, groups, selectedByGroup]);

    const selectionValid = useMemo(() => {
        if (!product) {
            return false;
        }

        return groups.every((group) => {
            const selectedCount = (selectedByGroup[group.id] ?? []).length;

            return isGroupSelectionValid(group, selectedCount);
        });
    }, [product, groups, selectedByGroup]);

    if (!product) {
        return null;
    }

    const toggleOption = (group: StorefrontOptionGroup, optionId: number) => {
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

    const buildSelection = (): SelectedProductOption[] => {
        const selectedOptions: SelectedProductOption[] = [];

        for (const group of groups) {
            const selectedIds = selectedByGroup[group.id] ?? [];

            for (const option of group.options) {
                const selected = selectedIds.includes(option.id);

                if (group.type === 'removable') {
                    if (!selected) {
                        selectedOptions.push({
                            option_id: option.id,
                            group_id: group.id,
                            name: option.name,
                            action: 'removed',
                            price_modifier: 0,
                        });
                    }

                    continue;
                }

                if (selected) {
                    selectedOptions.push({
                        option_id: option.id,
                        group_id: group.id,
                        name: option.name,
                        action:
                            group.type === 'addon' ? 'added' : 'selected',
                        price_modifier: option.price_modifier,
                    });
                }
            }
        }

        return selectedOptions;
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{product.name}</DialogTitle>
                </DialogHeader>

                <div className="space-y-5">
                    <p className="text-sm text-muted-foreground">
                        {product.description}
                    </p>

                    {groups.map((group) => {
                        const selectedIds = selectedByGroup[group.id] ?? [];
                        const selectedCount = selectedIds.length;
                        const atMax = selectedCount >= group.max_selection;
                        const hint = selectionHint(group);
                        const groupValid = isGroupSelectionValid(
                            group,
                            selectedCount,
                        );

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
                            <ul className="space-y-2">
                                {group.options.map((option) => {
                                    const selected = selectedIds.includes(
                                        option.id,
                                    );
                                    const optionDisabled =
                                        !selected &&
                                        atMax &&
                                        group.type !== 'removable';

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
                                                {option.price_modifier !== 0 ? (
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
                                                        toggleOption(
                                                            group,
                                                            option.id,
                                                        )
                                                    }
                                                />
                                                <Label
                                                    className={`font-normal ${optionDisabled ? 'text-muted-foreground' : ''}`}
                                                >
                                                    {option.name}
                                                </Label>
                                            </div>
                                            {group.type === 'addon' &&
                                            option.price_modifier !== 0 ? (
                                                <span className="text-sm text-muted-foreground">
                                                    +
                                                    {formatMoney(
                                                        option.price_modifier,
                                                    )}
                                                </span>
                                            ) : null}
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                        );
                    })}

                    {product.allow_special_instructions !== false ? (
                        <div className="space-y-2">
                            <Label htmlFor="product-note">Notas</Label>
                            <Textarea
                                id="product-note"
                                value={note}
                                onChange={(event) => setNote(event.target.value)}
                                placeholder="Ej. Bien cocida"
                                rows={3}
                            />
                            <p className="text-xs text-muted-foreground">
                                Solo se pueden modificar las opciones disponibles.
                            </p>
                        </div>
                    ) : null}

                    <div className="flex items-center justify-between gap-3">
                        <p className="text-sm font-medium text-navy">Cantidad</p>
                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                className="min-h-11 min-w-11"
                                onClick={() =>
                                    setQuantity((value) => Math.max(1, value - 1))
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
                                onClick={() => setQuantity((value) => value + 1)}
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
                            const selectedOptions = buildSelection();
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
                                    price: option.price_modifier,
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
                        Agregar al carrito · {formatMoney(unitTotal * quantity)}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
