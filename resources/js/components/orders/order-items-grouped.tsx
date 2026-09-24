import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import { notify } from '@/components/feedback/toast';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';

export type GroupedOrderItem = {
    id: number;
    quantity: string;
    product_name: string;
    display_name?: string | null;
    category_name?: string | null;
    subcategory_name?: string | null;
    subtotal: string;
    notes?: string | null;
    options?: Array<{ display: string }>;
};

type OrderItemGroup = {
    key: string;
    title: string | null;
    items: GroupedOrderItem[];
};

type OrderItemsGroupedProps = {
    items: GroupedOrderItem[];
    showPrices?: boolean;
    showCopyButton?: boolean;
    /** Optional lines above the products (order #, business, etc.) */
    copyPreamble?: string | null;
    className?: string;
};

function formatPieces(quantity: string): string {
    const numeric = Number(quantity);
    const label = Number.isFinite(numeric)
        ? Number.isInteger(numeric)
            ? String(numeric)
            : String(numeric).replace(/\.?0+$/, '')
        : quantity.replace(/\.00$/, '');

    return `${label}pzs.`;
}

function groupTitle(
    categoryName: string | null | undefined,
    subcategoryName: string | null | undefined,
): string | null {
    if (!categoryName) {
        return null;
    }

    if (subcategoryName) {
        return `${categoryName} -> ${subcategoryName}`;
    }

    return categoryName;
}

export function groupOrderItems(
    items: GroupedOrderItem[],
): OrderItemGroup[] {
    const groups: OrderItemGroup[] = [];
    const indexByKey = new Map<string, number>();

    for (const item of items) {
        const categoryName = item.category_name?.trim() || null;
        const subcategoryName = item.subcategory_name?.trim() || null;
        const key = categoryName
            ? `${categoryName.toLocaleLowerCase()}::${(subcategoryName ?? '').toLocaleLowerCase()}`
            : `__ungrouped__:${item.id}`;
        const existingIndex = indexByKey.get(key);

        if (existingIndex !== undefined) {
            groups[existingIndex].items.push(item);
            continue;
        }

        indexByKey.set(key, groups.length);
        groups.push({
            key,
            title: groupTitle(categoryName, subcategoryName),
            items: [item],
        });
    }

    return groups;
}

/**
 * Plain-text ticket for WhatsApp / clipboard. Never includes prices.
 */
export function formatOrderItemsPlainText(
    items: GroupedOrderItem[],
    preamble?: string | null,
): string {
    const groups = groupOrderItems(items);
    const lines: string[] = [];

    const intro = preamble?.trim();
    if (intro) {
        lines.push(intro);
        lines.push('');
    }

    groups.forEach((group, groupIndex) => {
        if (groupIndex > 0) {
            lines.push('');
        }

        if (group.title) {
            lines.push(group.title);
        }

        for (const item of group.items) {
            const name = item.display_name?.trim() || item.product_name;
            lines.push(`${formatPieces(item.quantity)} ${name}`);

            for (const option of item.options ?? []) {
                if (option.display.trim() !== '') {
                    lines.push(`  ${option.display}`);
                }
            }

            if (item.notes?.trim()) {
                lines.push(`  Nota: ${item.notes.trim()}`);
            }
        }
    });

    return lines.join('\n').trim();
}

function CopyOrderItemsButton({
    items,
    preamble,
}: {
    items: GroupedOrderItem[];
    preamble?: string | null;
}) {
    const [, copy] = useClipboard();
    const [justCopied, setJustCopied] = useState(false);

    return (
        <Button
            type="button"
            variant="outline"
            size="sm"
            className="gap-1.5"
            aria-label="Copiar pedido para WhatsApp"
            onClick={() => {
                const text = formatOrderItemsPlainText(items, preamble);

                void copy(text).then((copied) => {
                    if (copied) {
                        setJustCopied(true);
                        notify.success('Pedido copiado (sin precios)');
                        window.setTimeout(() => setJustCopied(false), 2000);
                    } else {
                        notify.error('No se pudo copiar el pedido');
                    }
                });
            }}
        >
            {justCopied ? (
                <Check className="size-4 text-success" />
            ) : (
                <Copy className="size-4" />
            )}
            {justCopied ? 'Copiado' : 'Copiar'}
        </Button>
    );
}

export function OrderItemsGrouped({
    items,
    showPrices = true,
    showCopyButton = false,
    copyPreamble = null,
    className,
}: OrderItemsGroupedProps) {
    const groups = groupOrderItems(items);

    return (
        <div className={cn('min-w-0 space-y-3', className)}>
            {showCopyButton ? (
                <div className="flex justify-end">
                    <CopyOrderItemsButton
                        items={items}
                        preamble={copyPreamble}
                    />
                </div>
            ) : null}

            {groups.map((group) => (
                <div key={group.key} className="min-w-0 space-y-2">
                    {group.title ? (
                        <h3 className="text-sm font-bold break-words text-navy">
                            {group.title}
                        </h3>
                    ) : null}
                    <ul className="min-w-0 space-y-2 text-sm">
                        {group.items.map((item) => {
                            const name =
                                item.display_name?.trim() ||
                                item.product_name;

                            return (
                                <li key={item.id} className="min-w-0 space-y-1">
                                    <div className="flex min-w-0 items-start justify-between gap-3">
                                        <span className="min-w-0 flex-1 break-words text-foreground">
                                            {formatPieces(item.quantity)} {name}
                                        </span>
                                        {showPrices ? (
                                            <span className="shrink-0 tabular-nums font-medium">
                                                {formatMoney(item.subtotal)}
                                            </span>
                                        ) : null}
                                    </div>
                                    {(item.options ?? []).length > 0 ? (
                                        <ul className="space-y-0.5 pl-3 text-xs break-words text-muted-foreground">
                                            {(item.options ?? []).map(
                                                (option, index) => (
                                                    <li
                                                        key={`${item.id}-opt-${index}`}
                                                    >
                                                        {option.display}
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    ) : null}
                                    {item.notes ? (
                                        <p className="pl-3 text-xs break-words text-muted-foreground">
                                            Nota: {item.notes}
                                        </p>
                                    ) : null}
                                </li>
                            );
                        })}
                    </ul>
                </div>
            ))}
        </div>
    );
}
