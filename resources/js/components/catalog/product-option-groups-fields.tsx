import { Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import type {
    ProductOptionClusterDraft,
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

function emptyCluster(): ProductOptionClusterDraft {
    return {
        name: '',
        options: [emptyOption('choice')],
    };
}

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

    const setHasClusters = (enabled: boolean) => {
        onChange(
            groups.map((group) => {
                if (group.type !== 'choice') {
                    return group;
                }

                if (enabled) {
                    const seedOptions =
                        group.options.length > 0
                            ? group.options
                            : [emptyOption('choice')];

                    return {
                        ...group,
                        has_option_clusters: true,
                        clusters:
                            group.clusters && group.clusters.length > 0
                                ? group.clusters
                                : [
                                      {
                                          name: 'Agrupación 1',
                                          options: seedOptions,
                                      },
                                  ],
                        options: seedOptions,
                    };
                }

                const flattened = (group.clusters ?? []).flatMap(
                    (cluster) => cluster.options,
                );

                return {
                    ...group,
                    has_option_clusters: false,
                    clusters: [],
                    options:
                        flattened.length > 0
                            ? flattened
                            : group.options.length > 0
                              ? group.options
                              : [emptyOption('choice')],
                };
            }),
        );
    };

    const updateCluster = (
        clusterIndex: number,
        patch: Partial<ProductOptionClusterDraft>,
    ) => {
        onChange(
            groups.map((group) => {
                if (group.type !== 'choice') {
                    return group;
                }

                const clusters = [...(group.clusters ?? [])];
                clusters[clusterIndex] = {
                    ...clusters[clusterIndex],
                    ...patch,
                };

                return {
                    ...group,
                    clusters,
                    options: clusters.flatMap((cluster) => cluster.options),
                };
            }),
        );
    };

    const updateClusterOption = (
        clusterIndex: number,
        optionIndex: number,
        patch: Partial<ProductOptionDraft>,
    ) => {
        onChange(
            groups.map((group) => {
                if (group.type !== 'choice') {
                    return group;
                }

                const clusters = (group.clusters ?? []).map(
                    (cluster, index) => {
                        if (index !== clusterIndex) {
                            return cluster;
                        }

                        return {
                            ...cluster,
                            options: cluster.options.map((option, optIndex) =>
                                optIndex === optionIndex
                                    ? { ...option, ...patch }
                                    : option,
                            ),
                        };
                    },
                );

                return {
                    ...group,
                    clusters,
                    options: clusters.flatMap((cluster) => cluster.options),
                };
            }),
        );
    };

    const addCluster = () => {
        onChange(
            groups.map((group) => {
                if (group.type !== 'choice') {
                    return group;
                }

                const clusters = [
                    ...(group.clusters ?? []),
                    emptyCluster(),
                ];

                return {
                    ...group,
                    clusters,
                    options: clusters.flatMap((cluster) => cluster.options),
                };
            }),
        );
    };

    const removeCluster = (clusterIndex: number) => {
        onChange(
            groups.map((group) => {
                if (group.type !== 'choice') {
                    return group;
                }

                const filtered = (group.clusters ?? []).filter(
                    (_, index) => index !== clusterIndex,
                );
                const clusters =
                    filtered.length === 0 ? [emptyCluster()] : filtered;

                return {
                    ...group,
                    clusters,
                    options: clusters.flatMap((cluster) => cluster.options),
                };
            }),
        );
    };

    const addClusterOption = (clusterIndex: number) => {
        onChange(
            groups.map((group) => {
                if (group.type !== 'choice') {
                    return group;
                }

                const clusters = (group.clusters ?? []).map(
                    (cluster, index) =>
                        index === clusterIndex
                            ? {
                                  ...cluster,
                                  options: [
                                      ...cluster.options,
                                      emptyOption('choice'),
                                  ],
                              }
                            : cluster,
                );

                return {
                    ...group,
                    clusters,
                    options: clusters.flatMap((cluster) => cluster.options),
                };
            }),
        );
    };

    const removeClusterOption = (
        clusterIndex: number,
        optionIndex: number,
    ) => {
        onChange(
            groups.map((group) => {
                if (group.type !== 'choice') {
                    return group;
                }

                const clusters = (group.clusters ?? []).map(
                    (cluster, index) => {
                        if (index !== clusterIndex) {
                            return cluster;
                        }

                        const filtered = cluster.options.filter(
                            (_, optIndex) => optIndex !== optionIndex,
                        );

                        return {
                            ...cluster,
                            options:
                                filtered.length === 0
                                    ? [emptyOption('choice')]
                                    : filtered,
                        };
                    },
                );

                return {
                    ...group,
                    clusters,
                    options: clusters.flatMap((cluster) => cluster.options),
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
                const hasClusters =
                    type === 'choice' && Boolean(group?.has_option_clusters);

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

                                {type === 'choice' ? (
                                    <label className="flex items-start gap-2 text-sm text-foreground">
                                        <Checkbox
                                            checked={hasClusters}
                                            onCheckedChange={(checked) =>
                                                setHasClusters(checked === true)
                                            }
                                            className="mt-0.5"
                                        />
                                        <span>
                                            Agrupar variantes
                                            <span className="mt-1 block text-xs text-muted-foreground">
                                                Opcional. Úsalo para dividir
                                                variantes en secciones (por
                                                ejemplo Picantes, Agridulces).
                                                El mínimo y máximo aplican al
                                                total de todas las
                                                agrupaciones.
                                            </span>
                                        </span>
                                    </label>
                                ) : null}

                                {hasClusters ? (
                                    <div className="space-y-4">
                                        {resolveFieldError(
                                            fieldKey(`${groupIndex}.clusters`),
                                            clientErrors,
                                            serverErrors,
                                        ) ? (
                                            <p className="text-sm text-destructive">
                                                {resolveFieldError(
                                                    fieldKey(
                                                        `${groupIndex}.clusters`,
                                                    ),
                                                    clientErrors,
                                                    serverErrors,
                                                )}
                                            </p>
                                        ) : null}
                                        {(group.clusters ?? []).map(
                                            (cluster, clusterIndex) => (
                                                <div
                                                    key={clusterIndex}
                                                    className="space-y-3 rounded-lg border border-border p-3"
                                                >
                                                    <div className="flex items-start gap-2">
                                                        <FormField
                                                            label="Nombre de la agrupación"
                                                            htmlFor={`cluster-name-${clusterIndex}`}
                                                            className="flex-1"
                                                            error={resolveFieldError(
                                                                fieldKey(
                                                                    `${groupIndex}.clusters.${clusterIndex}.name`,
                                                                ),
                                                                clientErrors,
                                                                serverErrors,
                                                            )}
                                                        >
                                                            <Input
                                                                id={`cluster-name-${clusterIndex}`}
                                                                value={
                                                                    cluster.name
                                                                }
                                                                placeholder="Ej. Picantes, Agridulces"
                                                                onChange={(
                                                                    event,
                                                                ) => {
                                                                    updateCluster(
                                                                        clusterIndex,
                                                                        {
                                                                            name: event
                                                                                .target
                                                                                .value,
                                                                        },
                                                                    );
                                                                    clearError(
                                                                        fieldKey(
                                                                            `${groupIndex}.clusters.${clusterIndex}.name`,
                                                                        ),
                                                                    );
                                                                }}
                                                            />
                                                        </FormField>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            className="mt-6 size-9 text-muted-foreground hover:text-destructive"
                                                            onClick={() =>
                                                                removeCluster(
                                                                    clusterIndex,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </Button>
                                                    </div>

                                                    <div className="space-y-2 border-l-2 border-border/70 pl-4 sm:pl-5">
                                                        <p className="text-sm font-medium text-foreground">
                                                            Variantes
                                                        </p>
                                                        {resolveFieldError(
                                                            fieldKey(
                                                                `${groupIndex}.clusters.${clusterIndex}.options`,
                                                            ),
                                                            clientErrors,
                                                            serverErrors,
                                                        ) ? (
                                                            <p className="text-sm text-destructive">
                                                                {resolveFieldError(
                                                                    fieldKey(
                                                                        `${groupIndex}.clusters.${clusterIndex}.options`,
                                                                    ),
                                                                    clientErrors,
                                                                    serverErrors,
                                                                )}
                                                            </p>
                                                        ) : null}
                                                        {cluster.options.map(
                                                            (
                                                                option,
                                                                optionIndex,
                                                            ) => (
                                                                <div
                                                                    key={
                                                                        optionIndex
                                                                    }
                                                                    className="grid grid-cols-[1fr_auto] items-start gap-2"
                                                                >
                                                                    <FormField
                                                                        error={resolveFieldError(
                                                                            fieldKey(
                                                                                `${groupIndex}.clusters.${clusterIndex}.options.${optionIndex}.name`,
                                                                            ),
                                                                            clientErrors,
                                                                            serverErrors,
                                                                        )}
                                                                    >
                                                                        <Input
                                                                            value={
                                                                                option.name
                                                                            }
                                                                            placeholder={
                                                                                config.optionPlaceholder
                                                                            }
                                                                            onChange={(
                                                                                event,
                                                                            ) => {
                                                                                updateClusterOption(
                                                                                    clusterIndex,
                                                                                    optionIndex,
                                                                                    {
                                                                                        name: event
                                                                                            .target
                                                                                            .value,
                                                                                    },
                                                                                );
                                                                                clearError(
                                                                                    fieldKey(
                                                                                        `${groupIndex}.clusters.${clusterIndex}.options.${optionIndex}.name`,
                                                                                    ),
                                                                                );
                                                                            }}
                                                                        />
                                                                    </FormField>
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="size-9 text-muted-foreground hover:text-destructive"
                                                                        onClick={() =>
                                                                            removeClusterOption(
                                                                                clusterIndex,
                                                                                optionIndex,
                                                                            )
                                                                        }
                                                                    >
                                                                        <Trash2 className="size-4" />
                                                                    </Button>
                                                                </div>
                                                            ),
                                                        )}
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                addClusterOption(
                                                                    clusterIndex,
                                                                )
                                                            }
                                                        >
                                                            + Agregar variante
                                                        </Button>
                                                    </div>
                                                </div>
                                            ),
                                        )}
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={addCluster}
                                        >
                                            + Agregar agrupación
                                        </Button>
                                    </div>
                                ) : (
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
                                        {group.options.map(
                                            (option, optionIndex) => (
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
                                                            onChange={(
                                                                event,
                                                            ) => {
                                                                updateOption(
                                                                    type,
                                                                    optionIndex,
                                                                    {
                                                                        name: event
                                                                            .target
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
                                                                onChange={(
                                                                    event,
                                                                ) => {
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
                                            ),
                                        )}
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => addOption(type)}
                                        >
                                            + Agregar opción
                                        </Button>
                                    </div>
                                )}
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
