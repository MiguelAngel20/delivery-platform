export type OrderItemLine = {
    quantity: string;
    product_name: string;
    line_label?: string | null;
    options?: Array<{ display: string }>;
};

/**
 * Formats order lines as: "3.00 - Tacos -> ASADA -> XX"
 */
export function formatOrderItemLine(item: OrderItemLine): string {
    if (typeof item.line_label === 'string' && item.line_label.trim() !== '') {
        return item.line_label;
    }

    const parts = [
        item.product_name,
        ...(item.options ?? []).map((option) => option.display),
    ].filter((part) => typeof part === 'string' && part.trim() !== '');

    return `${item.quantity} - ${parts.join(' -> ')}`;
}
