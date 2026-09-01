import type {
    SelectedProductOption,
    StorefrontOptionGroup,
    StorefrontProduct,
} from '@/apps/storefront/components/product-dialog';

export function isSingleChoiceGroup(group: StorefrontOptionGroup): boolean {
    return group.type === 'choice' && group.max_selection === 1;
}

export function selectionHint(group: StorefrontOptionGroup): string | null {
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

export function isGroupSelectionValid(
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

export function buildInitialOptionSelection(
    groups: StorefrontOptionGroup[],
): Record<number, number[]> {
    const initial: Record<number, number[]> = {};

    for (const group of groups) {
        if (group.type === 'removable') {
            initial[group.id] = group.options
                .filter((option) => option.is_default)
                .map((option) => option.id);
        } else if (group.type === 'choice') {
            const defaults = group.options.filter((option) => option.is_default);

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

    return initial;
}

export function isOptionSelectionValid(
    groups: StorefrontOptionGroup[],
    selectedByGroup: Record<number, number[]>,
): boolean {
    return groups.every((group) => {
        const selectedCount = (selectedByGroup[group.id] ?? []).length;

        return isGroupSelectionValid(group, selectedCount);
    });
}

export function buildSelectedProductOptions(
    groups: StorefrontOptionGroup[],
    selectedByGroup: Record<number, number[]>,
): SelectedProductOption[] {
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
                    action: group.type === 'addon' ? 'added' : 'selected',
                    price_modifier: option.price_modifier,
                });
            }
        }
    }

    return selectedOptions;
}

export function promotionItemNeedsCustomization(item: {
    option_groups?: StorefrontOptionGroup[];
    allow_special_instructions?: boolean;
}): boolean {
    return (
        (item.option_groups?.length ?? 0) > 0 ||
        item.allow_special_instructions === true
    );
}

export function itemToCustomizerProduct(item: {
    id: number;
    name: string;
    option_groups?: StorefrontOptionGroup[];
    allow_special_instructions?: boolean;
}): StorefrontProduct {
    return {
        id: item.id,
        name: item.name,
        description: '',
        price: 0,
        allow_special_instructions: item.allow_special_instructions,
        option_groups: item.option_groups ?? [],
    };
}
