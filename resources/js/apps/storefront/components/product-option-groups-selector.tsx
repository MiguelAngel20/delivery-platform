import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    isGroupSelectionValid,
    isSingleChoiceGroup,
    selectionHint,
} from '@/apps/storefront/components/product-option-selection';
import type { StorefrontOptionGroup } from '@/apps/storefront/components/product-dialog';
import { formatMoney } from '@/apps/storefront/mocks';

type ProductOptionGroupsSelectorProps = {
    groups: StorefrontOptionGroup[];
    selectedByGroup: Record<number, number[]>;
    onChange: (selectedByGroup: Record<number, number[]>) => void;
};

export function ProductOptionGroupsSelector({
    groups,
    selectedByGroup,
    onChange,
}: ProductOptionGroupsSelectorProps) {
    const toggleOption = (group: StorefrontOptionGroup, optionId: number) => {
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

    if (groups.length === 0) {
        return null;
    }

    return (
        <div className="space-y-5">
            {groups.map((group) => {
                const selectedIds = selectedByGroup[group.id] ?? [];
                const selectedCount = selectedIds.length;
                const atMax = selectedCount >= group.max_selection;
                const hint = selectionHint(group);
                const groupValid = isGroupSelectionValid(group, selectedCount);

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
                                const selected = selectedIds.includes(option.id);
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
        </div>
    );
}
