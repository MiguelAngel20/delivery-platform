import { Head } from '@inertiajs/react';
import { Percent, UtensilsCrossed } from 'lucide-react';
import { useState } from 'react';
import type { AddToCartInput } from '@/apps/storefront/cart/use-storefront-cart';
import { useStorefrontCart } from '@/apps/storefront/cart/use-storefront-cart';
import type { StorefrontProduct } from '@/apps/storefront/components/product-dialog';
import { ProductDialog } from '@/apps/storefront/components/product-dialog';
import { PromotionCard } from '@/apps/storefront/components/promotion-card';
import {
    RestaurantMenu,
    type RestaurantMenuCategory,
    type RestaurantMenuProduct,
} from '@/apps/storefront/components/restaurant-menu';
import { RestaurantPromotionsCarousel } from '@/apps/storefront/components/restaurant-promotions-carousel';
import {
    RestaurantProfile,
    type RestaurantProfileData,
} from '@/apps/storefront/components/restaurant-profile';
import { SwitchRestaurantDialog } from '@/apps/storefront/components/switch-restaurant-dialog';
import type { MockPromotion } from '@/apps/storefront/mocks';
import { EmptyState } from '@/components/feedback/empty-state';
import { PageContainer } from '@/components/layout/page';

type Product = StorefrontProduct &
    RestaurantMenuProduct & {
        restaurantSlug: string;
    };

type Promotion = {
    id: number;
    name: string;
    description: string | null;
    price: number;
    composition: string;
    image_url?: string | null;
};

type Props = {
    restaurant: RestaurantProfileData;
    branch_id: number;
    categories: RestaurantMenuCategory[];
    products: Product[];
    promotions: Promotion[];
};

export default function RestaurantShow({
    restaurant,
    branch_id,
    categories,
    products,
    promotions,
}: Props) {
    const { cart, addItem, replaceWithItem } = useStorefrontCart();
    const [selectedProduct, setSelectedProduct] = useState<Product | null>(
        null,
    );
    const [pendingAdd, setPendingAdd] = useState<AddToCartInput | null>(null);

    const confirmAdd = (input: AddToCartInput) => {
        const result = addItem(input);

        if (result === 'conflict') {
            setPendingAdd(input);
        }
    };

    return (
        <>
            <Head title={restaurant.name} />
            <PageContainer className="gap-6 px-4 py-4 md:px-6">
                <RestaurantProfile
                    restaurant={restaurant}
                    branchId={branch_id}
                />

                {promotions.length > 0 ? (
                    <section className="space-y-3">
                        <div className="flex items-center gap-2">
                            <span className="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Percent className="size-4" />
                            </span>
                            <div>
                                <h2 className="text-lg font-semibold text-navy">
                                    Promociones
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Ofertas activas de este negocio
                                </p>
                            </div>
                        </div>
                        <RestaurantPromotionsCarousel
                            promotions={promotions.map(
                                (promotion): MockPromotion => ({
                                    id: String(promotion.id),
                                    name: promotion.name,
                                    description: promotion.description ?? '',
                                    price: promotion.price,
                                    composition: promotion.composition,
                                    image_url: promotion.image_url,
                                }),
                            )}
                        />
                        <div className="hidden gap-3 md:grid md:grid-cols-2">
                            {promotions.map((promotion) => (
                                <PromotionCard
                                    key={promotion.id}
                                    promotion={{
                                        id: String(promotion.id),
                                        name: promotion.name,
                                        description:
                                            promotion.description ?? '',
                                        price: promotion.price,
                                        composition: promotion.composition,
                                        image_url: promotion.image_url,
                                    }}
                                />
                            ))}
                        </div>
                    </section>
                ) : null}

                <section className="space-y-4 md:space-y-5">
                    <div className="flex items-center gap-2">
                        <span className="flex size-8 items-center justify-center rounded-lg bg-accent text-navy">
                            <UtensilsCrossed className="size-4" />
                        </span>
                        <div>
                            <h2 className="text-lg font-semibold text-navy">
                                Menú
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Elige tus platillos favoritos
                            </p>
                        </div>
                    </div>
                    {products.length === 0 ? (
                        <EmptyState title="No hay productos" />
                    ) : (
                        <RestaurantMenu
                            categories={categories}
                            products={products}
                            canOrder={restaurant.canOrder && restaurant.open}
                            onAdd={(product) =>
                                setSelectedProduct(product as Product)
                            }
                        />
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
