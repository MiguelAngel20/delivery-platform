export type ProductOptionDraft = {
    name: string;
    description?: string;
    price_modifier: string;
    is_default: boolean;
    is_available: boolean;
};

export type ProductOptionGroupDraft = {
    name: string;
    type: string;
    is_required: boolean;
    min_selection: number;
    max_selection: number;
    is_active: boolean;
    options: ProductOptionDraft[];
};

export type SectionType = 'choice' | 'addon' | 'removable';

export type ProductOptionGroupApi = {
    id?: number;
    name: string;
    type: string;
    type_label?: string;
    is_required: boolean;
    min_selection: number;
    max_selection: number;
    sort_order?: number;
    is_active: boolean;
    options: Array<{
        id?: number;
        name: string;
        description?: string | null;
        price_modifier: string | number;
        is_default: boolean;
        is_available: boolean;
        sort_order?: number;
    }>;
};

export function mapApiOptionGroupsToDrafts(
    groups: ProductOptionGroupApi[],
): ProductOptionGroupDraft[] {
    return groups.map((group) => ({
        name: group.name,
        type: group.type,
        is_required: group.is_required,
        min_selection: group.min_selection,
        max_selection: group.max_selection,
        is_active: group.is_active,
        options: group.options.map((option) => ({
            name: option.name,
            description: option.description ?? undefined,
            price_modifier: String(option.price_modifier ?? '0'),
            is_default: option.is_default,
            is_available: option.is_available,
        })),
    }));
}
