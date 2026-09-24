/**
 * Normalize text for loose equality (case + accents).
 * "Taquería" and "Taqueria" compare equal.
 */
export function normalizeComparableText(value: string): string {
    return value
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .toLocaleLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

export function sameDisplayName(
    left: string | null | undefined,
    right: string | null | undefined,
): boolean {
    if (!left?.trim() || !right?.trim()) {
        return false;
    }

    return (
        normalizeComparableText(left) === normalizeComparableText(right)
    );
}

/**
 * Prefer a single business label; only append branch when it is meaningfully different.
 */
export function resolveRestaurantLabel(
    businessName: string | null | undefined,
    branchName: string | null | undefined,
    fallback = 'Negocio',
): string {
    const business = businessName?.trim() || fallback;
    const branch = branchName?.trim() || null;

    if (!branch || sameDisplayName(business, branch)) {
        return business;
    }

    return `${business} · ${branch}`;
}
