import type {
    MockCategory,
    MockProduct,
    MockPromotion,
    MockRestaurant,
} from '@/apps/storefront/mocks';

export type SearchProduct = Pick<
    MockProduct,
    'id' | 'restaurantSlug' | 'category' | 'name' | 'description' | 'ingredients'
>;

export type SearchPromotion = Pick<
    MockPromotion,
    'id' | 'restaurantSlug' | 'name' | 'description' | 'composition'
>;

export type StorefrontSearchCatalog = {
    restaurants: MockRestaurant[];
    products?: SearchProduct[];
    promotions?: SearchPromotion[];
    categories?: MockCategory[];
};

export function normalizeSearchText(value: string): string {
    return value
        .toLowerCase()
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .trim();
}

function matchesWords(haystack: string, words: string[]): boolean {
    const text = normalizeSearchText(haystack);

    return words.every((word) => text.includes(word));
}

/**
 * Storefront search against the catalog passed from the search page props.
 */
export function searchStorefrontRestaurants(
    query: string,
    catalog: StorefrontSearchCatalog,
): MockRestaurant[] {
    const normalized = normalizeSearchText(query);
    const words = normalized.split(/\s+/).filter(Boolean);

    if (words.length === 0) {
        return [];
    }

    const categories = catalog.categories ?? [];
    const products = catalog.products ?? [];
    const promotions = catalog.promotions ?? [];

    const matchedCategoryNames = new Set(
        categories
            .filter(
                (category) =>
                    matchesWords(category.name, words) ||
                    matchesWords(category.slug.replaceAll('-', ' '), words),
            )
            .map((category) => normalizeSearchText(category.name)),
    );

    return catalog.restaurants.filter((restaurant) => {
        if (
            matchesWords(restaurant.name, words) ||
            matchesWords(restaurant.slug.replaceAll('-', ' '), words) ||
            matchesWords(restaurant.category, words) ||
            matchesWords(restaurant.branchName, words) ||
            matchesWords(restaurant.modeLabel, words)
        ) {
            return true;
        }

        if (matchedCategoryNames.has(normalizeSearchText(restaurant.category))) {
            return true;
        }

        const productHit = products.some((product) => {
            if (product.restaurantSlug !== restaurant.slug) {
                return false;
            }

            return (
                matchesWords(product.name, words) ||
                matchesWords(product.description, words) ||
                matchesWords(product.category, words) ||
                product.ingredients.some((ingredient) =>
                    matchesWords(ingredient, words),
                )
            );
        });

        if (productHit) {
            return true;
        }

        return promotions.some(
            (promotion) =>
                promotion.restaurantSlug === restaurant.slug &&
                (matchesWords(promotion.name, words) ||
                    matchesWords(promotion.description, words) ||
                    matchesWords(promotion.composition, words)),
        );
    });
}

export function searchStorefrontCategories(
    query: string,
    categories: MockCategory[],
): MockCategory[] {
    const words = normalizeSearchText(query).split(/\s+/).filter(Boolean);

    if (words.length === 0) {
        return [];
    }

    return categories.filter(
        (category) =>
            matchesWords(category.name, words) ||
            matchesWords(category.slug.replaceAll('-', ' '), words),
    );
}
