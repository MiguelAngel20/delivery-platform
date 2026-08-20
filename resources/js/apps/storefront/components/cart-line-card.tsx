import { Minus, Pencil, Plus } from 'lucide-react';
import {
    cartLineTotal,
    getCartLineCustomizations,
} from '@/apps/storefront/cart/cart-line-customizations';
import type { CartLine } from '@/apps/storefront/cart/use-storefront-cart';
import { formatMoney } from '@/apps/storefront/mocks';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type CartLineCardProps = {
    line: CartLine;
    onUpdateQuantity?: (quantity: number) => void;
    onEdit?: () => void;
    editLoading?: boolean;
    compact?: boolean;
    className?: string;
};

export function CartLineCard({
    line,
    onUpdateQuantity,
    onEdit,
    editLoading = false,
    compact = false,
    className,
}: CartLineCardProps) {
    const customizations = getCartLineCustomizations(line);
    const lineTotal = cartLineTotal(line);

    return (
        <article
            className={cn(
                'overflow-hidden rounded-2xl border border-border bg-surface shadow-sm',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-3 p-4">
                <div className="min-w-0 space-y-2">
                    <div>
                        <p className="font-semibold text-navy">{line.name}</p>
                        {!compact ? (
                            <p className="text-xs text-muted-foreground">
                                {formatMoney(line.unitPrice)} c/u
                            </p>
                        ) : null}
                    </div>

                    {customizations.variants.length > 0 ? (
                        <div className="space-y-1">
                            <p className="text-xs font-medium text-muted-foreground">
                                Variantes
                            </p>
                            <div className="flex flex-wrap gap-1.5">
                                {customizations.variants.map((variant) => (
                                    <Badge
                                        key={variant.name}
                                        variant="secondary"
                                        className="font-normal"
                                    >
                                        {variant.name}
                                        {variant.price
                                            ? ` (+${formatMoney(variant.price)})`
                                            : ''}
                                    </Badge>
                                ))}
                            </div>
                        </div>
                    ) : null}

                    {customizations.extras.length > 0 ? (
                        <div className="space-y-1">
                            <p className="text-xs font-medium text-muted-foreground">
                                Extras
                            </p>
                            <ul className="space-y-0.5 text-sm text-navy">
                                {customizations.extras.map((extra) => (
                                    <li key={extra.name}>
                                        + {extra.name}
                                        {extra.price
                                            ? ` (${formatMoney(extra.price)})`
                                            : ''}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ) : null}

                    {customizations.removed.length > 0 ? (
                        <div className="space-y-1">
                            <p className="text-xs font-medium text-muted-foreground">
                                Sin
                            </p>
                            <ul className="space-y-0.5 text-sm text-destructive">
                                {customizations.removed.map((item) => (
                                    <li key={item}>− {item}</li>
                                ))}
                            </ul>
                        </div>
                    ) : null}

                    {customizations.note ? (
                        <p className="rounded-lg bg-muted/60 px-2.5 py-1.5 text-xs text-muted-foreground italic">
                            Nota: {customizations.note}
                        </p>
                    ) : null}
                </div>

                <p className="shrink-0 text-base font-semibold text-navy">
                    {formatMoney(lineTotal)}
                </p>
            </div>

            {!compact ? (
                <div className="flex items-center justify-between gap-3 border-t border-border bg-muted/30 px-4 py-3">
                    {onEdit ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="gap-1.5 text-muted-foreground"
                            onClick={onEdit}
                            disabled={editLoading}
                        >
                            <Pencil className="size-3.5" />
                            {editLoading ? 'Cargando…' : 'Editar'}
                        </Button>
                    ) : (
                        <span className="text-sm text-muted-foreground">
                            Cantidad
                        </span>
                    )}
                    <div className="flex items-center gap-2">
                        {onEdit ? (
                            <span className="text-sm text-muted-foreground">
                                Cantidad
                            </span>
                        ) : null}
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            className="size-9 rounded-full"
                            onClick={() =>
                                onUpdateQuantity?.(line.quantity - 1)
                            }
                            aria-label="Disminuir cantidad"
                        >
                            <Minus className="size-4" />
                        </Button>
                        <span className="w-8 text-center font-semibold text-navy">
                            {line.quantity}
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            className="size-9 rounded-full"
                            onClick={() =>
                                onUpdateQuantity?.(line.quantity + 1)
                            }
                            aria-label="Aumentar cantidad"
                        >
                            <Plus className="size-4" />
                        </Button>
                    </div>
                </div>
            ) : (
                <div className="border-t border-border px-4 py-2 text-sm text-muted-foreground">
                    {line.quantity} {line.quantity === 1 ? 'unidad' : 'unidades'}
                </div>
            )}
        </article>
    );
}
