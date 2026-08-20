import type { CartLine } from '@/apps/storefront/cart/use-storefront-cart';
import type { StorefrontProduct } from '@/apps/storefront/components/product-dialog';

export function buildInitialSelectionFromCartLine(
    product: StorefrontProduct,
    line: CartLine,
): Record<number, number[]> {
    const initial: Record<number, number[]> = {};
    const selections = line.selectedOptions ?? [];
    const removedIds = new Set(
        selections
            .filter((option) => option.action === 'removed')
            .map((option) => option.option_id),
    );
    const removedNames = new Set(line.removedIngredients ?? []);

    for (const group of product.option_groups ?? []) {
        if (group.type === 'removable') {
            initial[group.id] = group.options
                .filter(
                    (option) =>
                        !removedIds.has(option.id) &&
                        !removedNames.has(option.name) &&
                        option.is_default,
                )
                .map((option) => option.id);

            continue;
        }

        if (group.type === 'choice') {
            initial[group.id] = selections
                .filter(
                    (option) =>
                        option.action === 'selected' &&
                        group.options.some(
                            (candidate) => candidate.id === option.option_id,
                        ),
                )
                .map((option) => option.option_id);

            continue;
        }

        if (group.type === 'addon') {
            const selected = selections
                .filter(
                    (option) =>
                        option.action === 'added' &&
                        group.options.some(
                            (candidate) => candidate.id === option.option_id,
                        ),
                )
                .map((option) => option.option_id);

            for (const extra of line.extras) {
                const option = group.options.find(
                    (candidate) => String(candidate.id) === extra.id,
                );

                if (option && !selected.includes(option.id)) {
                    selected.push(option.id);
                }
            }

            initial[group.id] = selected;
        }
    }

    return initial;
}
