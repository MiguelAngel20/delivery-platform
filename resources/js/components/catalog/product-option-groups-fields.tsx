import { Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import type {
    ProductOptionDraft,
    ProductOptionGroupDraft,
    SectionType,
} from '@/components/catalog/product-option-group-types';
import {
    buildGroup,
    emptyOption,
    findGroupIndex,
    SECTION_CONFIG,
    SECTION_ORDER,
} from '@/components/catalog/product-option-groups-config';
import { FormField } from '@/components/forms/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { resolveFieldError } from '@/lib/catalog/validate-product-form';

type NumericLimitInputProps = {
    id: string;
    value: number;
    minAllowed: number;
    onCommit: (value: number) => void;
};

function NumericLimitInput({
    id,
    value,
    minAllowed,
    onCommit,
}: NumericLimitInputProps) {
    const [draft, setDraft] = useState(String(value));

    useEffect(() => {
        setDraft(String(value));
    }, [value]);

    const commit = () => {
        const parsed = draft === '' ? minAllowed : parseInt(draft, 10);
        const normalized = Number.isNaN(parsed)
            ? minAllowed
            : Math.max(minAllowed, parsed);

        onCommit(normalized);
        setDraft(String(normalized));
    };

    return (
        <Input
            id={id}
            type="text"
            inputMode="numeric"
            value={draft}
            onChange={(event) => {
                const next = event.target.value;

                if (next === '' || /^\d+$/.test(next)) {
                    setDraft(next);
                }
            }}
            onBlur={commit}
        />
    );
}

type ProductOptionGroupsFieldsProps = {
    groups: ProductOptionGroupDraft[];
    onChange: (groups: ProductOptionGroupDraft[]) => void;
    errorPrefix?: string;
    clientErrors?: Record<string, string>;
    serverErrors?: Record<string, string>;
    onClearError?: (key: string) => void;
    heading?: string;
    description?: string;
};

export function ProductOptionGroupsFields({
    groups,
    onChange,
    errorPrefix = 'option_groups',
    clientErrors = {},
    serverErrors = {},
    onClearError,
    heading = 'Personalización',
    description = 'Marca las opciones que aplican. Si no marcas ninguna, el producto se vende tal cual.',
}: ProductOptionGroupsFieldsProps) {
    const fieldKey = (suffix: string) =>
        suffix === '' ? errorPrefix : `${errorPrefix}.${suffix}`;

    const clearError = (key: string) => {
        onClearError?.(key);
    };

    const toggleSection = (type: SectionType, enabled: boolean) => {
        if (enabled) {
            if (findGroupIndex(groups, type) !== -1) {
                return;
            }

            onChange([...groups, buildGroup(type)]);

            return;
        }

        onChange(groups.filter((group) => group.type !== type));
    };

    const updateGroupByType = (
        type: SectionType,
        patch: Partial<ProductOptionGroupDraft>,
    ) => {
        onChange(
            groups.map((group) =>
                group.type === type ? { ...group, ...patch } : group,
            ),
        );
    };

    const updateOption = (
        type: SectionType,
        optionIndex: number,
        patch: Partial<ProductOptionDraft>,
    ) => {
        onChange(
            groups.map((group) => {
                if (group.type !== type) {
                    return group;
                }

                return {
                    ...group,
                    options: group.options.map((option, index) =>
                        index === optionIndex ? { ...option, ...patch } : option,
                    ),
                };
            }),
        );
    };

    const addOption = (type: SectionType) => {
        onChange(
            groups.map((group) => {
                if (group.type !== type) {
                    return group;
                }

                return {
                    ...group,
                    options: [...group.options, emptyOption(type)],
                };
            }),
        );
    };

    const removeOption = (type: SectionType, optionIndex: number) => {
        onChange(
            groups.map((group) => {
                if (group.type !== type) {
                    return group;
                }

                const filtered = group.options.filter(
                    (_, index) => index !== optionIndex,
                );

                return {
                    ...group,
                    options:
                        filtered.length === 0
                            ? [emptyOption(type)]
                            : filtered,
                };
            }),
        );
    };

    return (
        <section className="space-y-4 rounded-xl border border-border bg-surface p-4">
            <div>
                <h2 className="text-base font-semibold text-foreground">
                    {heading}
                </h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>

            {SECTION_ORDER.map((type) => {
                const config = SECTION_CONFIG[type];
                const groupIndex = findGroupIndex(groups, type);
                const group = groupIndex === -1 ? undefined : groups[groupIndex];
                const isEnabled = group !== undefined;

                return (
                    <div key={type} className="rounded-lg border border-border">
                        <label className="flex cursor-pointer items-start gap-3 p-4 text-foreground">
                            <Checkbox
                                checked={isEnabled}
                                onCheckedChange={(checked) =>
                                    toggleSection(type, checked === true)
                                }
                                className="mt-0.5"
                            />
                            <div>
                                <span className="text-sm font-medium text-foreground">
                                    {config.label}
                                </span>
                                <p className="text-sm text-muted-foreground">
                                    {config.description}
                                </p>
                            </div>
                        </label>

                        {isEnabled && group ? (
                            <div className="space-y-3 border-t border-border px-4 pb-4 pt-3">
                                {config.showLimits ? (
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <FormField
                                            label="Mínimo que debe elegir"
                                            htmlFor={`${type}-min`}
                                        >
                                            <NumericLimitInput
                                                id={`${type}-min`}
                                                value={group.min_selection}
                                                minAllowed={0}
                                                onCommit={(min) =>
                                                    updateGroupByType(type, {
                                                        min_selection: min,
                                                    })
                                                }
                                            />
                                        </FormField>
                                        <FormField
                                            label="Máximo que puede elegir"
                                            htmlFor={`${type}-max`}
                                            error={resolveFieldError(
                                                fieldKey(
                                                    `${groupIndex}.max_selection`,
                                                ),
                                                clientErrors,
                                                serverErrors,
                                            )}
                                        >
                                            <NumericLimitInput
                                                id={`${type}-max`}
                                                value={group.max_selection}
                                                minAllowed={1}
                                                onCommit={(max) =>
                                                    updateGroupByType(type, {
                                                        max_selection: max,
                                                    })
                                                }
                                            />
                                        </FormField>
                                    </div>
                                ) : null}

                                <div className="space-y-2">
                                    <p className="text-sm font-medium text-foreground">
                                        Opciones
                                    </p>
                                    {groupIndex !== -1 &&
                                    resolveFieldError(
                                        fieldKey(`${groupIndex}.options`),
                                        clientErrors,
                                        serverErrors,
                                    ) ? (
                                        <p className="text-sm text-destructive">
                                            {resolveFieldError(
                                                fieldKey(
                                                    `${groupIndex}.options`,
                                                ),
                                                clientErrors,
                                                serverErrors,
                                            )}
                                        </p>
                                    ) : null}
                                    {group.options.map((option, optionIndex) => (
                                        <div
                                            key={optionIndex}
                                            className={`grid items-start gap-2 ${config.showPrice ? 'grid-cols-[1fr_100px_auto]' : 'grid-cols-[1fr_auto]'}`}
                                        >
                                            <FormField
                                                error={resolveFieldError(
                                                    fieldKey(
                                                        `${groupIndex}.options.${optionIndex}.name`,
                                                    ),
                                                    clientErrors,
                                                    serverErrors,
                                                )}
                                            >
                                                <Input
                                                    value={option.name}
                                                    placeholder={
                                                        config.optionPlaceholder
                                                    }
                                                    onChange={(event) => {
                                                        updateOption(
                                                            type,
                                                            optionIndex,
                                                            {
                                                                name: event.target
                                                                    .value,
                                                            },
                                                        );
                                                        clearError(
                                                            fieldKey(
                                                                `${groupIndex}.options`,
                                                            ),
                                                        );
                                                        clearError(
                                                            fieldKey(
                                                                `${groupIndex}.options.${optionIndex}.name`,
                                                            ),
                                                        );
                                                    }}
                                                />
                                            </FormField>
                                            {config.showPrice ? (
                                                <FormField
                                                    error={resolveFieldError(
                                                        fieldKey(
                                                            `${groupIndex}.options.${optionIndex}.price_modifier`,
                                                        ),
                                                        clientErrors,
                                                        serverErrors,
                                                    )}
                                                >
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        value={
                                                            option.price_modifier
                                                        }
                                                        placeholder="+ $0.00"
                                                        onChange={(event) => {
                                                            updateOption(
                                                                type,
                                                                optionIndex,
                                                                {
                                                                    price_modifier:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                },
                                                            );
                                                            clearError(
                                                                fieldKey(
                                                                    `${groupIndex}.options.${optionIndex}.price_modifier`,
                                                                ),
                                                            );
                                                        }}
                                                    />
                                                </FormField>
                                            ) : null}
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-9 text-muted-foreground hover:text-destructive"
                                                onClick={() =>
                                                    removeOption(
                                                        type,
                                                        optionIndex,
                                                    )
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    ))}
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => addOption(type)}
                                    >
                                        + Agregar opción
                                    </Button>
                                </div>
                            </div>
                        ) : null}
                    </div>
                );
            })}
            {resolveFieldError(errorPrefix, clientErrors, serverErrors) ? (
                <p className="text-sm text-destructive">
                    {resolveFieldError(
                        errorPrefix,
                        clientErrors,
                        serverErrors,
                    )}
                </p>
            ) : null}
        </section>
    );
}
