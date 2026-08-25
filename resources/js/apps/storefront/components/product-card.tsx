import { Plus } from 'lucide-react';
import { formatMoney } from '@/apps/storefront/mocks';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type ProductCardProduct = {
    id: string;
    name: string;
    description: string;
    price: number;
    image_url?: string | null;
};

type ProductCardProps = {
    product: ProductCardProduct;
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
                'flex gap-2.5 rounded-xl border border-border bg-surface p-2 shadow-sm transition-all hover:border-primary/30 hover:shadow-md md:gap-3 md:rounded-2xl md:p-3',
                className,
            )}
        >
            <div className="relative size-16 shrink-0 overflow-hidden rounded-lg bg-secondary md:size-28 md:rounded-xl">
                {product.image_url ? (
                    <img
                        src={product.image_url}
                        alt={product.name}
                        className="size-full object-cover"
                    />
                ) : (
                    <div className="flex size-full items-center justify-center bg-gradient-to-br from-accent to-secondary text-lg font-semibold text-navy md:text-2xl">
                        {product.name.slice(0, 1)}
                    </div>
                )}
            </div>
            <div className="flex min-w-0 flex-1 flex-col gap-1 md:gap-2">
                <div className="space-y-0.5 md:space-y-1">
                    <h3 className="text-sm leading-snug font-semibold text-navy md:text-base">
                        {product.name}
                    </h3>
                    {product.description ? (
                        <p className="line-clamp-2 text-xs leading-snug text-muted-foreground md:text-sm">
                            {product.description}
                        </p>
                    ) : null}
                </div>
                <div className="mt-auto flex items-center justify-between gap-2">
                    <span className="text-sm font-semibold text-primary md:text-base">
                        {formatMoney(product.price)}
                    </span>
                    {canOrder ? (
                        <Button
                            type="button"
                            size="sm"
                            className="size-8 shrink-0 rounded-full p-0 md:h-10 md:w-auto md:min-h-10 md:px-4"
                            aria-label={`Agregar ${product.name}`}
                            onClick={onAdd}
                        >
                            <Plus className="size-4" />
                            <span className="hidden md:inline">Agregar</span>
                        </Button>
                    ) : null}
                </div>
            </div>
        </article>
    );
}
