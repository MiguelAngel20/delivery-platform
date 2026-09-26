import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    addonQuantityForOption,
    groupSelectionCount,
    isGroupSelectionValid,
    isSingleChoiceGroup,
    selectionHint,
} from '@/apps/storefront/components/product-option-selection';
import type {
    StorefrontOptionGroup,
    StorefrontProductOption,
} from '@/apps/storefront/components/product-dialog';
import { formatMoney } from '@/apps/storefront/mocks';

type ProductOptionGroupsSelectorProps = {
    groups: StorefrontOptionGroup[];
    selectedByGroup: Record<number, number[]>;
    optionQuantities?: Record<number, number>;
    onChange: (selectedByGroup: Record<number, number[]>) => void;
    onOptionQuantitiesChange?: (optionQuantities: Record<number, number>) => void;
};

export function ProductOptionGroupsSelector({
    groups,
    selectedByGroup,
    optionQuantities = {},
    onChange,
    onOptionQuantitiesChange,
}: ProductOptionGroupsSelectorProps) {
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
        const usedWithoutThis = groupSelectionCount(
            group,
            [...selected],
            optionQuantities,
        ) - currentQty;

        if (nextQuantity <= 0) {
            selected.delete(optionId);
            const nextQuantities = { ...optionQuantities };
            delete nextQuantities[optionId];
            onChange({ ...selectedByGroup, [group.id]: [...selected] });
            onOptionQuantitiesChange?.(nextQuantities);

            return;
        }

        if (usedWithoutThis + nextQuantity > group.max_selection) {
            return;
        }

        selected.add(optionId);
        onChange({ ...selectedByGroup, [group.id]: [...selected] });
        onOptionQuantitiesChange?.({
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

            if (currentQty > 0) {
                setAddonQuantity(group, optionId, 0);
            } else {
                setAddonQuantity(group, optionId, 1);
            }

            return;
        }

        const selected = new Set(selectedByGroup[group.id] ?? []);

        if (isSingleChoiceGroup(group)) {
            onChange({ ...selectedByGroup, [group.id]: [optionId] });

            return;
        }

        if (selected.has(optionId)) {
            selected.delete(optionId);
        } else {
            if (selected.size >= group.max_selection) {
                return;
            }

            selected.add(optionId);
        }

        onChange({ ...selectedByGroup, [group.id]: [...selected] });
    };

    const renderOption = (
        group: StorefrontOptionGroup,
        option: StorefrontProductOption,
        selectedIds: number[],
        selectedCount: number,
        atMax: boolean,
    ) => {
        const selected = selectedIds.includes(option.id);
        const optionDisabled =
            !selected && atMax && group.type !== 'removable';

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
                            onChange={() => toggleOption(group, option.id)}
                        />
                        {option.name}
                    </label>
                    {option.price_modifier !== 0 ? (
                        <span className="text-sm text-muted-foreground">
                            +{formatMoney(option.price_modifier)}
                        </span>
                    ) : null}
                </li>
            );
        }

        if (group.type === 'addon') {
            const quantity = addonQuantityForOption(
                option.id,
                selectedIds,
                optionQuantities,
            );
            const canIncrease = selectedCount < group.max_selection;

            return (
                <li
                    key={option.id}
                    className="flex items-center justify-between gap-3"
                >
                    <div className="flex min-w-0 flex-1 items-center gap-2">
                        {quantity > 0 ? (
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
                                            quantity - 1,
                                        )
                                    }
                                >
                                    -
                                </Button>
                                <span className="w-5 text-center text-sm font-semibold">
                                    {quantity}
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
                                            quantity + 1,
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
                                    toggleOption(group, option.id)
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
                                option.price_modifier * Math.max(1, quantity),
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
                        onCheckedChange={() => toggleOption(group, option.id)}
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

    if (groups.length === 0) {
        return null;
    }

    return (
        <div className="space-y-5">
            {groups.map((group) => {
                const selectedIds = selectedByGroup[group.id] ?? [];
                const selectedCount = groupSelectionCount(
                    group,
                    selectedIds,
                    optionQuantities,
                );
                const atMax = selectedCount >= group.max_selection;
                const hint = selectionHint(group);
                const groupValid = isGroupSelectionValid(group, selectedCount);
                const showClusters =
                    Boolean(group.has_option_clusters) &&
                    (group.clusters?.length ?? 0) > 0;

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
                                    <div key={cluster.id} className="space-y-2">
                                        <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                            {cluster.name}
                                        </p>
                                        <ul className="space-y-2">
                                            {cluster.options.map((option) =>
                                                renderOption(
                                                    group,
                                                    option,
                                                    selectedIds,
                                                    selectedCount,
                                                    atMax,
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <ul className="space-y-2">
                                {group.options.map((option) =>
                                    renderOption(
                                        group,
                                        option,
                                        selectedIds,
                                        selectedCount,
                                        atMax,
                                    ),
                                )}
                            </ul>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
