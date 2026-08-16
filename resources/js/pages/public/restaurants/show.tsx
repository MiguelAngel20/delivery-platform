import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { AddToCartInput } from '@/apps/storefront/cart/use-storefront-cart';
import { useStorefrontCart } from '@/apps/storefront/cart/use-storefront-cart';
import { ProductCard } from '@/apps/storefront/components/product-card';
import type { StorefrontProduct } from '@/apps/storefront/components/product-dialog';
import { ProductDialog } from '@/apps/storefront/components/product-dialog';
import { PromotionCard } from '@/apps/storefront/components/promotion-card';
import { SwitchRestaurantDialog } from '@/apps/storefront/components/switch-restaurant-dialog';
import { StatusBadge } from '@/components/data-display/status-badge';
import { EmptyState } from '@/components/feedback/empty-state';
import { PageContainer } from '@/components/layout/page';

type Restaurant = {
    id: number;
    slug: string;
    name: string;
    category: string;
    eta: string;
    open: boolean;
    mode: string;
    branchName: string;
    schedule: string;
    canOrder: boolean;
    modeLabel: string;
    description?: string | null;
    branches: Array<{ id: number; name: string }>;
};

type Product = StorefrontProduct & {
    restaurantSlug: string;
    category: string;
    is_available?: boolean;
};

type Promotion = {
    id: number;
    name: string;
    description: string | null;
    price: number;
    composition: string;
};

type Props = {
    restaurant: Restaurant;
    branch_id: number;
    categories: Array<{ id: number; name: string; description?: string | null }>;
    products: Product[];
    promotions: Promotion[];
};

export default function RestaurantShow({
    restaurant,
    branch_id,
    products,
    promotions,
}: Props) {
    const { cart, addItem, replaceWithItem } = useStorefrontCart();
    const [selectedProduct, setSelectedProduct] = useState<Product | null>(null);
    const [pendingAdd, setPendingAdd] = useState<AddToCartInput | null>(null);

    const grouped = useMemo(() => {
        return products.reduce<Record<string, Product[]>>((groups, product) => {
            groups[product.category] ??= [];
            groups[product.category].push(product);

            return groups;
        }, {});
    }, [products]);

    const confirmAdd = (input: AddToCartInput) => {
        const result = addItem(input);

        if (result === 'conflict') {
            setPendingAdd(input);
        }
    };

    return (
        <>
            <Head title={restaurant.name} />
            <PageContainer className="gap-5 px-4 py-4 md:px-6">
                <section className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                    <div className="flex h-32 items-end bg-secondary px-4 py-4">
                        <h1 className="text-2xl font-semibold text-navy">
                            {restaurant.name}
                        </h1>
                    </div>
                    <div className="space-y-2 p-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusBadge
                                tone={restaurant.open ? 'success' : 'neutral'}
                            >
                                {restaurant.open ? 'Abierto' : 'Cerrado'}
                            </StatusBadge>
                            <span className="text-sm text-muted-foreground">
                                {restaurant.category}
                            </span>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {restaurant.branchName} · {restaurant.schedule}
                        </p>
                        <p className="text-sm font-medium text-navy">
                            {restaurant.eta} · {restaurant.modeLabel}
                        </p>
                        {!restaurant.open ? (
                            <p className="rounded-md border border-border bg-secondary px-3 py-2 text-sm text-navy">
                                Este negocio está cerrado ahora. Puedes ver el
                                menú, pero no agregar productos al carrito hasta
                                que abra.
                            </p>
                        ) : null}
                        {restaurant.branches.length > 1 ? (
                            <select
                                className="mt-2 flex h-10 w-full max-w-sm rounded-md border border-input bg-background px-3 text-sm"
                                value={branch_id}
                                onChange={(event) =>
                                    router.get(
                                        `/restaurants/${restaurant.slug}`,
                                        { branch: event.target.value },
                                        { preserveState: true },
                                    )
                                }
                            >
                                {restaurant.branches.map((branch) => (
                                    <option key={branch.id} value={branch.id}>
                                        {branch.name}
                                    </option>
                                ))}
                            </select>
                        ) : null}
                    </div>
                </section>

                {promotions.length > 0 ? (
                    <section className="space-y-3">
                        <h2 className="text-lg font-semibold text-navy">
                            Promociones
                        </h2>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {promotions.map((promotion) => (
                                <PromotionCard
                                    key={promotion.id}
                                    promotion={{
                                        id: String(promotion.id),
                                        name: promotion.name,
                                        description: promotion.description ?? '',
                                        price: promotion.price,
                                        composition: promotion.composition,
                                    }}
                                />
                            ))}
                        </div>
                    </section>
                ) : null}

                <section className="space-y-5">
                    <h2 className="text-lg font-semibold text-navy">Menú</h2>
                    {Object.keys(grouped).length === 0 ? (
                        <EmptyState title="No hay productos" />
                    ) : (
                        Object.entries(grouped).map(([category, items]) => (
                            <div key={category} className="space-y-3">
                                <h3 className="font-semibold text-navy">
                                    {category}
                                </h3>
                                <div className="grid gap-3">
                                    {items.map((product) => (
                                        <ProductCard
                                            key={product.id}
                                            product={{
                                                id: String(product.id),
                                                restaurantSlug:
                                                    product.restaurantSlug,
                                                category: product.category,
                                                name: product.name,
                                                description: product.description,
                                                price: product.price,
                                                ingredients: [],
                                                extras: [],
                                            }}
                                            canOrder={
                                                restaurant.canOrder &&
                                                restaurant.open &&
                                                product.is_available !== false
                                            }
                                            onAdd={() =>
                                                setSelectedProduct(product)
                                            }
                                        />
                                    ))}
                                </div>
                            </div>
                        ))
                    )}
                </section>
            </PageContainer>

            <ProductDialog
                product={selectedProduct}
                open={selectedProduct !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelectedProduct(null);
                    }
                }}
                onConfirm={(payload) => {
                    if (!selectedProduct) {
                        return;
                    }

                    confirmAdd({
                        product: {
                            id: selectedProduct.id,
                            branchId: branch_id,
                            restaurantSlug: selectedProduct.restaurantSlug,
                            restaurantName: restaurant.name,
                            restaurantMode: restaurant.mode,
                            name: selectedProduct.name,
                            price: selectedProduct.price,
                        },
                        ...payload,
                    });
                }}
            />

            <SwitchRestaurantDialog
                open={pendingAdd !== null}
                currentRestaurant={cart.restaurantName ?? undefined}
                nextRestaurant={pendingAdd ? restaurant.name : undefined}
                onCancel={() => setPendingAdd(null)}
                onConfirm={() => {
                    if (pendingAdd) {
                        replaceWithItem(pendingAdd);
                    }

                    setPendingAdd(null);
                }}
            />
        </>
    );
}
