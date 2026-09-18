import { Head, Link, router, usePage } from '@inertiajs/react';
import { ShoppingBag, Store } from 'lucide-react';
import { useState } from 'react';
import { setCheckoutIntent, useStorefrontCart } from '@/apps/storefront/cart/use-storefront-cart';
import type { CartLine } from '@/apps/storefront/cart/use-storefront-cart';
import { isPromotionCartLine } from '@/apps/storefront/cart/use-storefront-cart';
import { CartLineCard } from '@/apps/storefront/components/cart-line-card';
import { CheckoutFooter } from '@/apps/storefront/components/checkout-footer';
import { CheckoutStepper } from '@/apps/storefront/components/checkout-stepper';
import type { StorefrontProduct } from '@/apps/storefront/components/product-dialog';
import { ProductDialog } from '@/apps/storefront/components/product-dialog';
import { PromotionDialog } from '@/apps/storefront/components/promotion-dialog';
import { CoverageUnavailableBanner } from '@/apps/storefront/components/coverage-unavailable-banner';
import { OrderSummary } from '@/apps/storefront/components/order-summary';
import { useDeliveryLocation } from '@/apps/storefront/hooks/use-delivery-location';
import { useServiceFeeQuote } from '@/apps/storefront/hooks/use-service-fee-quote';
import { COVERAGE_UNAVAILABLE_MESSAGE } from '@/apps/storefront/lib/check-delivery-coverage';
import type { ActivitySuspensionShared } from '@/apps/storefront/components/activity-suspension-modal';
import { notify } from '@/components/feedback/toast';
import { EmptyState } from '@/components/feedback/empty-state';
import { PageContainer } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { create as register } from '@/actions/App/Http/Controllers/Web/Auth/CustomerRegisterController';
import { cart as cartRoute } from '@/routes';
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
    const { auth, activitySuspension } = usePage().props as {
        auth: Auth;
        activitySuspension?: ActivitySuspensionShared | null;
    };
    const orderingSuspended = activitySuspension?.active === true;
    const { location, hasCoordinates } = useDeliveryLocation();
    const { cart, updateQuantity, replaceLine, clear } = useStorefrontCart();
    const feeQuote = useServiceFeeQuote(
        hasCoordinates ? location.latitude : null,
        hasCoordinates ? location.longitude : null,
        cart.branchId,
    );
    const { subtotal, service, serviceFeeDiscount, discount, total } =
        useStorefrontCart({
            serviceFeeOverride: feeQuote?.serviceFee ?? null,
            serviceFeeDiscountOverride: feeQuote?.serviceFeeDiscount ?? null,
        });
    const isCustomer = auth.user?.role === 'customer';
    const checkoutHref = isCustomer ? customer.checkout() : register();

    const [editingLine, setEditingLine] = useState<CartLine | null>(null);
    const [editProduct, setEditProduct] = useState<StorefrontProduct | null>(
        null,
    );
    const [editContext, setEditContext] = useState<CartProductResponse | null>(
        null,
    );
    const [editingPromotionId, setEditingPromotionId] = useState<number | null>(
        null,
    );
    const [editingPromotionLine, setEditingPromotionLine] =
        useState<CartLine | null>(null);

    const outsideCoverage = feeQuote?.covered === false;

    const handleContinue = () => {
        if (orderingSuspended) {
            notify.error(
                activitySuspension?.footnote ??
                    'Por el momento no es posible realizar pedidos nuevos.',
            );

            return;
        }

        if (outsideCoverage) {
            notify.error(
                feeQuote?.message ?? COVERAGE_UNAVAILABLE_MESSAGE,
            );

            return;
        }

        if (!isCustomer) {
            // Resume at cart (paso 1) after login/register so line items stay visible.
            setCheckoutIntent(cartRoute.url());
        }

        router.visit(checkoutHref);
    };

    const [editLoadingKey, setEditLoadingKey] = useState<string | null>(null);

    const openEdit = async (line: CartLine) => {
        if (isPromotionCartLine(line)) {
            setEditingPromotionLine(line);
            setEditingPromotionId(Number(line.promotionId));

            return;
        }

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
        setEditingPromotionId(null);
        setEditingPromotionLine(null);
    };

    const promotionEditLine =
        editingPromotionLine && isPromotionCartLine(editingPromotionLine)
            ? editingPromotionLine
            : null;

    return (
        <>
            <Head title="Carrito" />
            <PageContainer className="gap-5 px-4 py-4 pb-32 md:px-6 md:pb-6">
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
                            serviceFeeDiscount={serviceFeeDiscount}
                            discount={discount}
                        />
                        {feeQuote?.distanceMeters != null && !outsideCoverage ? (
                            <p className="text-xs text-muted-foreground">
                                Servicio según distancia (
                                {(feeQuote.distanceMeters / 1000).toFixed(1)} km)
                            </p>
                        ) : null}

                        {outsideCoverage ? (
                            <CoverageUnavailableBanner
                                message={feeQuote?.message}
                            />
                        ) : null}

                        <CheckoutFooter
                            total={total}
                            primaryLabel="Continuar"
                            onPrimary={handleContinue}
                            primaryDisabled={
                                outsideCoverage || orderingSuspended
                            }
                        />
                    </>
                )}
            </PageContainer>

            <PromotionDialog
                promotionId={editingPromotionId}
                open={promotionEditLine !== null}
                editSelections={promotionEditLine?.promotionItems}
                editQuantity={promotionEditLine?.quantity}
                confirmLabel="Guardar cambios"
                onOpenChange={(open) => {
                    if (!open) {
                        closeEdit();
                    }
                }}
                onConfirm={({ promotion, quantity, promotionItems }) => {
                    if (!promotionEditLine) {
                        return;
                    }

                    replaceLine(promotionEditLine.key, {
                        promotion: {
                            id: promotion.id,
                            branchId: promotionEditLine.branchId,
                            restaurantSlug: promotionEditLine.restaurantSlug,
                            restaurantName: promotionEditLine.restaurantName,
                            name: promotion.name,
                            price: promotion.price,
                            composition: promotion.composition,
                        },
                        quantity,
                        promotionItems,
                    });

                    notify.success('Promoción actualizada.');
                    closeEdit();
                }}
            />

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
