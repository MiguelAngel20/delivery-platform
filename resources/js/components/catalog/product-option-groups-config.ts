import type {
    ProductOptionDraft,
    ProductOptionGroupDraft,
    SectionType,
} from '@/components/catalog/product-option-group-types';

export const SECTION_CONFIG: Record<
    SectionType,
    {
        label: string;
        description: string;
        showPrice: boolean;
        showLimits: boolean;
        defaultMin: number;
        defaultMax: number;
        isRequired: boolean;
        optionPlaceholder: string;
    }
> = {
    choice: {
        label: 'Variantes',
        description:
            'Sabores, salsas, ingredientes o especialidades que el cliente puede elegir.',
        showPrice: false,
        showLimits: true,
        defaultMin: 1,
        defaultMax: 1,
        isRequired: true,
        optionPlaceholder: 'Ej. BBQ, Hawaiana, Pepperoni',
    },
    addon: {
        label: 'Extras',
        description:
            'Complementos que el cliente puede agregar, con o sin costo adicional.',
        showPrice: true,
        showLimits: true,
        defaultMin: 0,
        defaultMax: 5,
        isRequired: false,
        optionPlaceholder: 'Ej. Extra queso, Aderezo ranch',
    },
    removable: {
        label: 'Quitar ingredientes',
        description:
            'Ingredientes que el cliente puede pedir que se retiren del producto.',
        showPrice: false,
        showLimits: false,
        defaultMin: 0,
        defaultMax: 99,
        isRequired: false,
        optionPlaceholder: 'Ej. Sin cebolla, Sin jalapeño',
    },
};

export const SECTION_ORDER: SectionType[] = ['choice', 'addon', 'removable'];

export function emptyOption(type: SectionType): ProductOptionDraft {
    return {
        name: '',
        price_modifier: '0',
        is_default: type === 'removable',
        is_available: true,
    };
}

export function buildGroup(type: SectionType): ProductOptionGroupDraft {
    const config = SECTION_CONFIG[type];

    return {
        name: config.label,
        type,
        is_required: config.isRequired,
        min_selection: config.defaultMin,
        max_selection: config.defaultMax,
        is_active: true,
        options: [emptyOption(type)],
    };
}

export function findGroupIndex(
    groups: ProductOptionGroupDraft[],
    type: SectionType,
): number {
    return groups.findIndex((group) => group.type === type);
}
