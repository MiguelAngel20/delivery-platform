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
                'flex gap-3 rounded-2xl border border-border bg-surface p-3 shadow-sm transition-all hover:border-primary/30 hover:shadow-md',
                className,
            )}
        >
            <div className="relative size-24 shrink-0 overflow-hidden rounded-xl bg-secondary sm:size-28">
                {product.image_url ? (
                    <img
                        src={product.image_url}
                        alt={product.name}
                        className="size-full object-cover"
                    />
                ) : (
                    <div className="flex size-full items-center justify-center bg-gradient-to-br from-accent to-secondary text-2xl font-semibold text-navy">
                        {product.name.slice(0, 1)}
                    </div>
                )}
            </div>
            <div className="flex min-w-0 flex-1 flex-col gap-2">
                <div className="space-y-1">
                    <h3 className="font-semibold text-navy">{product.name}</h3>
                    {product.description ? (
                        <p className="line-clamp-2 text-sm text-muted-foreground">
                            {product.description}
                        </p>
                    ) : null}
                </div>
                <div className="mt-auto flex items-center justify-between gap-2">
                    <span className="text-base font-semibold text-primary">
                        {formatMoney(product.price)}
                    </span>
                    {canOrder ? (
                        <Button
                            type="button"
                            size="sm"
                            className="min-h-10 rounded-full px-4"
                            onClick={onAdd}
                        >
                            <Plus className="size-4" />
                            Agregar
                        </Button>
                    ) : null}
                </div>
            </div>
        </article>
    );
}
