import type { ProductOptionGroupDraft } from '@/components/catalog/product-option-group-types';

export type ProductFormClientErrors = Record<string, string>;

const SECTION_LABELS: Record<string, string> = {
    choice: 'Variantes',
    addon: 'Extras',
    removable: 'Quitar ingredientes',
};

export function sanitizeProductOptionGroups(
    groups: ProductOptionGroupDraft[],
): ProductOptionGroupDraft[] {
    return groups
        .map((group) => ({
            ...group,
            options: group.options.filter(
                (option) => option.name.trim() !== '',
            ),
        }))
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
        const namedOptions = group.options.filter(
            (option) => option.name.trim() !== '',
        );

        if (namedOptions.length === 0) {
            errors[`${errorPrefix}.${groupIndex}.options`] =
                `Agrega al menos una opción en "${sectionLabel}".`;
        }

        if (group.min_selection > group.max_selection) {
            errors[`${errorPrefix}.${groupIndex}.max_selection`] =
                'El máximo no puede ser menor que el mínimo.';
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

            if (group.type === 'addon' && option.price_modifier.trim() !== '') {
                const modifier = Number(option.price_modifier);

                if (Number.isNaN(modifier) || modifier < 0) {
                    errors[
                        `${errorPrefix}.${groupIndex}.options.${optionIndex}.price_modifier`
                    ] = 'Ingresa un precio adicional válido.';
                }
            }
        });
    });

    return errors;
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
