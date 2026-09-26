import type { ProductOptionGroupDraft } from '@/components/catalog/product-option-group-types';

export type ProductFormClientErrors = Record<string, string>;

const SECTION_LABELS: Record<string, string> = {
    choice: 'Variantes',
    addon: 'Extras',
    removable: 'Quitar ingredientes',
    size: 'Tamaños / porciones',
};

export function sanitizeProductOptionGroups(
    groups: ProductOptionGroupDraft[],
): ProductOptionGroupDraft[] {
    return groups
        .map((group) => {
            const hasClusters =
                group.type === 'choice' && Boolean(group.has_option_clusters);

            if (hasClusters) {
                const clusters = (group.clusters ?? [])
                    .map((cluster) => ({
                        name: cluster.name.trim(),
                        options: cluster.options.filter(
                            (option) => option.name.trim() !== '',
                        ),
                    }))
                    .filter(
                        (cluster) =>
                            cluster.name !== '' && cluster.options.length > 0,
                    );

                return {
                    ...group,
                    has_option_clusters: true,
                    clusters,
                    options: clusters.flatMap((cluster) => cluster.options),
                };
            }

            return {
                ...group,
                has_option_clusters: false,
                clusters: [],
                options: group.options.filter(
                    (option) => option.name.trim() !== '',
                ),
            };
        })
        .filter((group) => group.options.length > 0);
}

export function resolveFieldError(
    key: string,
    clientErrors: ProductFormClientErrors,
    serverErrors: Record<string, string>,
): string | undefined {
    return clientErrors[key] ?? serverErrors[key];
}

export function validateProductOptionGroups(
    groups: ProductOptionGroupDraft[],
    errorPrefix = 'option_groups',
): ProductFormClientErrors {
    const errors: ProductFormClientErrors = {};

    groups.forEach((group, groupIndex) => {
        const sectionLabel =
            SECTION_LABELS[group.type] ?? group.name ?? 'Personalización';
        const hasClusters =
            group.type === 'choice' && Boolean(group.has_option_clusters);

        if (hasClusters) {
            const clusters = group.clusters ?? [];

            if (clusters.length === 0) {
                errors[`${errorPrefix}.${groupIndex}.clusters`] =
                    `Agrega al menos una agrupación en "${sectionLabel}".`;
            }

            clusters.forEach((cluster, clusterIndex) => {
                if (cluster.name.trim() === '') {
                    errors[
                        `${errorPrefix}.${groupIndex}.clusters.${clusterIndex}.name`
                    ] = 'Cada agrupación debe tener un nombre.';
                }

                const namedOptions = cluster.options.filter(
                    (option) => option.name.trim() !== '',
                );

                if (namedOptions.length === 0) {
                    errors[
                        `${errorPrefix}.${groupIndex}.clusters.${clusterIndex}.options`
                    ] = 'Cada agrupación debe tener al menos una variante.';
                }

                cluster.options.forEach((option, optionIndex) => {
                    const optionName = option.name.trim();

                    if (optionName === '') {
                        return;
                    }

                    if (optionName.length > 100) {
                        errors[
                            `${errorPrefix}.${groupIndex}.clusters.${clusterIndex}.options.${optionIndex}.name`
                        ] =
                            'El nombre de la opción no puede superar 100 caracteres.';
                    }
                });
            });
        } else {
            const namedOptions = group.options.filter(
                (option) => option.name.trim() !== '',
            );

            if (namedOptions.length === 0) {
                errors[`${errorPrefix}.${groupIndex}.options`] =
                    `Agrega al menos una opción en "${sectionLabel}".`;
            }

            group.options.forEach((option, optionIndex) => {
                const optionName = option.name.trim();

                if (optionName === '') {
                    return;
                }

                if (optionName.length > 100) {
                    errors[
                        `${errorPrefix}.${groupIndex}.options.${optionIndex}.name`
                    ] = 'El nombre de la opción no puede superar 100 caracteres.';
                }

                if (
                    (group.type === 'addon' || group.type === 'size') &&
                    option.price_modifier.trim() !== ''
                ) {
                    const modifier = Number(option.price_modifier);

                    if (Number.isNaN(modifier) || modifier < 0) {
                        errors[
                            `${errorPrefix}.${groupIndex}.options.${optionIndex}.price_modifier`
                        ] =
                            group.type === 'size'
                                ? 'Ingresa un precio válido para este tamaño.'
                                : 'Ingresa un precio adicional válido.';
                    }
                }

                if (
                    group.type === 'size' &&
                    option.price_modifier.trim() === ''
                ) {
                    errors[
                        `${errorPrefix}.${groupIndex}.options.${optionIndex}.price_modifier`
                    ] = 'Ingresa un precio válido para este tamaño.';
                }
            });
        }

        if (group.min_selection > group.max_selection) {
            errors[`${errorPrefix}.${groupIndex}.max_selection`] =
                'El máximo no puede ser menor que el mínimo.';
        }
    });

    return errors;
}

export function sizeGroupMinPrice(
    groups: ProductOptionGroupDraft[],
): string | null {
    const sizeGroup = groups.find((group) => group.type === 'size');

    if (!sizeGroup) {
        return null;
    }

    const prices = sizeGroup.options
        .filter((option) => option.name.trim() !== '')
        .map((option) => Number(option.price_modifier))
        .filter((price) => !Number.isNaN(price) && price >= 0);

    if (prices.length === 0) {
        return null;
    }

    return Math.min(...prices).toFixed(2);
}

export function validateProductForm(input: {
    branchId: string;
    name: string;
    listPrice: string;
    isEditing: boolean;
    groups: ProductOptionGroupDraft[];
}): ProductFormClientErrors {
    const errors: ProductFormClientErrors = {};

    if (!input.isEditing && input.branchId.trim() === '') {
        errors.branch_id = 'Selecciona una sucursal.';
    }

    const name = input.name.trim();

    if (name === '') {
        errors.name = 'El nombre del producto es obligatorio.';
    } else if (name.length > 150) {
        errors.name = 'El nombre no puede superar 150 caracteres.';
    }

    const listPrice = input.listPrice.trim();

    if (listPrice === '') {
        errors.list_price = 'El precio de lista es obligatorio.';
    } else {
        const parsed = Number(listPrice);

        if (Number.isNaN(parsed) || parsed < 0) {
            errors.list_price =
                'Ingresa un precio de lista válido mayor o igual a 0.';
        }
    }

    return {
        ...errors,
        ...validateProductOptionGroups(input.groups),
    };
}
