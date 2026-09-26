import type { CartLine } from '@/apps/storefront/cart/use-storefront-cart';
import { isPromotionCartLine } from '@/apps/storefront/cart/use-storefront-cart';

export type CartLineCustomization = {
    variants: Array<{ name: string; price?: number }>;
    extras: Array<{ name: string; price?: number }>;
    removed: string[];
    note?: string;
    itemNotes?: Array<{ name: string; note: string }>;
};

function formatExtraLabel(name: string, quantity = 1): string {
    const qty = Math.max(1, quantity);

    return qty > 1 ? `${qty} ${name}` : name;
}

export function getCartLineCustomizations(
    line: CartLine,
): CartLineCustomization {
    if (isPromotionCartLine(line)) {
        const variants: Array<{ name: string; price?: number }> = [];
        const extras: Array<{ name: string; price?: number }> = [];
        const removed: string[] = [];
        const itemNotes: Array<{ name: string; note: string }> = [];

        for (const item of line.promotionItems) {
            for (const option of item.selectedOptions ?? []) {
                const label = `${item.name}: ${option.name}`;

                if (option.action === 'selected') {
                    variants.push({
                        name: label,
                        price:
                            option.price_modifier !== 0
                                ? option.price_modifier
                                : undefined,
                    });
                }

                if (option.action === 'added') {
                    const quantity = Math.max(1, option.quantity ?? 1);
                    extras.push({
                        name: formatExtraLabel(label, quantity),
                        price:
                            option.price_modifier !== 0
                                ? option.price_modifier * quantity
                                : undefined,
                    });
                }

                if (option.action === 'removed') {
                    removed.push(label);
                }
            }

            const trimmedNote = item.note?.trim();

            if (trimmedNote) {
                itemNotes.push({ name: item.name, note: trimmedNote });
            }
        }

        return {
            variants,
            extras,
            removed,
            note: line.note,
            itemNotes,
        };
    }

    const variants: Array<{ name: string; price?: number }> = [];
    const extras: Array<{ name: string; price?: number }> = [];
    const removed = [...(line.removedIngredients ?? [])];

    for (const option of line.selectedOptions ?? []) {
        if (option.action === 'selected') {
            variants.push({
                name: option.name,
                price:
                    option.price_modifier !== 0
                        ? option.price_modifier
                        : undefined,
            });
        }

        if (option.action === 'added') {
            const quantity = Math.max(1, option.quantity ?? 1);
            extras.push({
                name: formatExtraLabel(option.name, quantity),
                price:
                    option.price_modifier !== 0
                        ? option.price_modifier * quantity
                        : undefined,
            });
        }

        if (option.action === 'removed' && !removed.includes(option.name)) {
            removed.push(option.name);
        }
    }

    const coveredExtraIds = new Set(
        (line.selectedOptions ?? [])
            .filter((option) => option.action === 'added')
            .map((option) => String(option.option_id)),
    );

    for (const extra of line.extras) {
        if (coveredExtraIds.has(extra.id)) {
            continue;
        }

        if (!extras.some((item) => item.name === extra.name)) {
            extras.push({ name: extra.name, price: extra.price });
        }
    }

    return {
        variants,
        extras,
        removed,
        note: line.note,
    };
}

export function cartLineTotal(line: CartLine): number {
    if (isPromotionCartLine(line)) {
        return line.unitPrice * line.quantity;
    }

    const extrasTotal = line.extras.reduce(
        (sum, extra) => sum + extra.price,
        0,
    );

    return (line.unitPrice + extrasTotal) * line.quantity;
}
