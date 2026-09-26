export type ProductOptionDraft = {
    name: string;
    description?: string;
    price_modifier: string;
    is_default: boolean;
    is_available: boolean;
};

export type ProductOptionClusterDraft = {
    name: string;
    options: ProductOptionDraft[];
};

export type ProductOptionGroupDraft = {
    name: string;
    type: string;
    is_required: boolean;
    min_selection: number;
    max_selection: number;
    is_active: boolean;
    has_option_clusters?: boolean;
    clusters?: ProductOptionClusterDraft[];
    options: ProductOptionDraft[];
};

export type SectionType = 'choice' | 'addon' | 'removable' | 'size';

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
    has_option_clusters?: boolean;
    clusters?: Array<{
        id?: number;
        name: string;
        sort_order?: number;
        options: Array<{
            id?: number;
            name: string;
            description?: string | null;
            price_modifier: string | number;
            is_default: boolean;
            is_available: boolean;
            sort_order?: number;
        }>;
    }>;
    options: Array<{
        id?: number;
        name: string;
        description?: string | null;
        price_modifier: string | number;
        is_default: boolean;
        is_available: boolean;
        sort_order?: number;
        option_cluster_id?: number | null;
    }>;
};

function mapOptionDraft(option: {
    name: string;
    description?: string | null;
    price_modifier: string | number;
    is_default: boolean;
    is_available: boolean;
}): ProductOptionDraft {
    return {
        name: option.name,
        description: option.description ?? undefined,
        price_modifier: String(option.price_modifier ?? '0'),
        is_default: option.is_default,
        is_available: option.is_available,
    };
}

export function mapApiOptionGroupsToDrafts(
    groups: ProductOptionGroupApi[],
): ProductOptionGroupDraft[] {
    return groups.map((group) => {
        const hasClusters =
            group.type === 'choice' && Boolean(group.has_option_clusters);

        const clusters = hasClusters
            ? (group.clusters ?? []).map((cluster) => ({
                  name: cluster.name,
                  options: cluster.options.map(mapOptionDraft),
              }))
            : [];

        return {
            name: group.name,
            type: group.type,
            is_required: group.is_required,
            min_selection: group.min_selection,
            max_selection: group.max_selection,
            is_active: group.is_active,
            has_option_clusters: hasClusters,
            clusters,
            options: group.options.map(mapOptionDraft),
        };
    });
}
