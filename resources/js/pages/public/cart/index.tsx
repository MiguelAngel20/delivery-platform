import { Head, Link, router, usePage } from '@inertiajs/react';
import { ShoppingBag, Store } from 'lucide-react';
import { useState } from 'react';
import {
    setCheckoutIntent,
    useStorefrontCart,
} from '@/apps/storefront/cart/use-storefront-cart';
import type { CartLine } from '@/apps/storefront/cart/use-storefront-cart';
import { CartLineCard } from '@/apps/storefront/components/cart-line-card';
import { CheckoutFooter } from '@/apps/storefront/components/checkout-footer';
import { CheckoutStepper } from '@/apps/storefront/components/checkout-stepper';
import type { StorefrontProduct } from '@/apps/storefront/components/product-dialog';
import { ProductDialog } from '@/apps/storefront/components/product-dialog';
import { OrderSummary } from '@/apps/storefront/components/order-summary';
import { notify } from '@/components/feedback/toast';
import { EmptyState } from '@/components/feedback/empty-state';
import { PageContainer } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';
import customer from '@/routes/customer';
import restaurants from '@/routes/restaurants';
import type { Auth } from '@/types';

type CartProductResponse = {
    product: StorefrontProduct;
    branch_id: number;
    restaurant: {
        name: string;
        slug: string;
        mode: string;
    };
};

export default function CartIndex() {
    const { auth } = usePage().props as { auth: Auth };
    const {
        cart,
        subtotal,
        service,
        discount,
        total,
        updateQuantity,
        replaceLine,
        clear,
    } = useStorefrontCart();
    const isCustomer = auth.user?.role === 'customer';
    const checkoutHref = isCustomer ? customer.checkout() : login();

    const [editingLine, setEditingLine] = useState<CartLine | null>(null);
    const [editProduct, setEditProduct] = useState<StorefrontProduct | null>(
        null,
    );
    const [editContext, setEditContext] = useState<CartProductResponse | null>(
        null,
    );
    const [editLoadingKey, setEditLoadingKey] = useState<string | null>(null);

    const handleContinue = () => {
        if (!isCustomer) {
            setCheckoutIntent(customer.checkout.url());
        }

        router.visit(checkoutHref);
    };

    const openEdit = async (line: CartLine) => {
        setEditLoadingKey(line.key);

        try {
            const response = await fetch(`/cart/products/${line.productId}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Product unavailable');
            }

            const data = (await response.json()) as CartProductResponse;

            setEditingLine(line);
            setEditProduct(data.product);
            setEditContext(data);
        } catch {
            notify.error(
                'No se pudo cargar el producto. Intenta de nuevo o vuelve al menú.',
            );
        } finally {
            setEditLoadingKey(null);
        }
    };

    const closeEdit = () => {
        setEditingLine(null);
        setEditProduct(null);
        setEditContext(null);
    };

    return (
        <>
            <Head title="Carrito" />
            <PageContainer className="gap-5 px-4 py-4 pb-28 md:px-6 md:pb-32">
                <CheckoutStepper currentStep={1} />

                {cart.lines.length === 0 ? (
                    <EmptyState
                        title="Carrito vacío"
                        description="Agrega productos desde un restaurante para comenzar tu pedido."
                        action={
                            <Button asChild>
                                <Link href={restaurants.index()}>
                                    Ver restaurantes
                                </Link>
                            </Button>
                        }
                    />
                ) : (
                    <>
                        <div className="flex items-start justify-between gap-3">
                            <div className="space-y-1">
                                <div className="flex items-center gap-2">
                                    <ShoppingBag className="size-5 text-primary" />
                                    <h1 className="text-2xl font-semibold text-navy">
                                        Tu pedido
                                    </h1>
                                </div>
                                {cart.restaurantName ? (
                                    <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                        <Store className="size-3.5" />
                                        {cart.restaurantName}
                                    </p>
                                ) : null}
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="text-muted-foreground"
                                onClick={clear}
                            >
                                Vaciar
                            </Button>
                        </div>

                        <ul className="space-y-3">
                            {cart.lines.map((line) => (
                                <li key={line.key}>
                                    <CartLineCard
                                        line={line}
                                        onUpdateQuantity={(quantity) =>
                                            updateQuantity(line.key, quantity)
                                        }
                                        onEdit={() => openEdit(line)}
                                        editLoading={
                                            editLoadingKey === line.key
                                        }
                                    />
                                </li>
                            ))}
                        </ul>

                        <OrderSummary
                            subtotal={subtotal}
                            service={service}
                            discount={discount}
                            total={total}
                        />

                        <CheckoutFooter
                            total={total}
                            primaryLabel="Confirmar pedido"
                            onPrimary={handleContinue}
                        />
                    </>
                )}
            </PageContainer>

            <ProductDialog
                product={editProduct}
                open={editProduct !== null && editingLine !== null}
                editLine={editingLine}
                confirmLabel="Guardar cambios"
                onOpenChange={(open) => {
                    if (!open) {
                        closeEdit();
                    }
                }}
                onConfirm={(payload) => {
                    if (!editingLine || !editProduct || !editContext) {
                        return;
                    }

                    replaceLine(editingLine.key, {
                        product: {
                            id: editProduct.id,
                            branchId: editContext.branch_id,
                            restaurantSlug: editProduct.restaurantSlug ?? '',
                            restaurantName: editContext.restaurant.name,
                            restaurantMode: editContext.restaurant.mode,
                            name: editProduct.name,
                            price: editProduct.price,
                        },
                        quantity: payload.quantity,
                        extras: payload.extras,
                        note: payload.note,
                        removedIngredients: payload.removedIngredients,
                        selectedOptions: payload.selectedOptions,
                    });

                    notify.success('Producto actualizado.');
                    closeEdit();
                }}
            />
        </>
    );
}
