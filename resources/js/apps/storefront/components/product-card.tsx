import type { MockProduct } from '@/apps/storefront/mocks';
import { formatMoney } from '@/apps/storefront/mocks';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type ProductCardProps = {
    product: MockProduct;
    canOrder?: boolean;
    onAdd?: () => void;
    className?: string;
};

export function ProductCard({
    product,
    canOrder = true,
    onAdd,
    className,
}: ProductCardProps) {
    return (
        <article
            className={cn(
                'flex gap-3 rounded-xl border border-border bg-surface p-3 shadow-sm',
                className,
            )}
        >
            <div className="flex size-20 shrink-0 items-center justify-center rounded-lg bg-secondary text-sm font-semibold text-navy">
                {product.name.slice(0, 1)}
            </div>
            <div className="flex min-w-0 flex-1 flex-col gap-2">
                <div className="space-y-1">
                    <h3 className="font-semibold text-navy">{product.name}</h3>
                    <p className="line-clamp-2 text-sm text-muted-foreground">
                        {product.description}
                    </p>
                </div>
                <div className="mt-auto flex items-center justify-between gap-2">
                    <span className="font-semibold text-navy">
                        {formatMoney(product.price)}
                    </span>
                    {canOrder ? (
                        <Button
                            type="button"
                            size="sm"
                            className="min-h-10"
                            onClick={onAdd}
                        >
                            Agregar
                        </Button>
                    ) : null}
                </div>
            </div>
        </article>
    );
}
