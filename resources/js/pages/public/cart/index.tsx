import { Head, Link, usePage } from '@inertiajs/react';
import {
    setCheckoutIntent,
    useStorefrontCart,
} from '@/apps/storefront/cart/use-storefront-cart';
import { OrderSummary } from '@/apps/storefront/components/order-summary';
import { formatMoney } from '@/apps/storefront/mocks';
import { EmptyState } from '@/components/feedback/empty-state';
import { PageContainer } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';
import customer from '@/routes/customer';
import restaurants from '@/routes/restaurants';
import type { Auth } from '@/types';

export default function CartIndex() {
    const { auth } = usePage().props as { auth: Auth };
    const { cart, subtotal, service, discount, total, updateQuantity, clear } =
        useStorefrontCart();
    const isCustomer = auth.user?.role === 'customer';

    const continueCheckout = () => {
        if (!isCustomer) {
            setCheckoutIntent(customer.checkout.url());
        }
    };

    return (
        <>
            <Head title="Carrito" />
            <PageContainer className="gap-4 px-4 py-4 md:px-6">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold text-navy">
                            Carrito
                        </h1>
                        {cart.restaurantName ? (
                            <p className="text-sm text-muted-foreground">
                                {cart.restaurantName}
                            </p>
                        ) : null}
                    </div>
                    {cart.lines.length > 0 ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={clear}
                        >
                            Vaciar
                        </Button>
                    ) : null}
                </div>

                {cart.lines.length === 0 ? (
                    <EmptyState
                        title="Carrito vacío"
                        description="Agrega productos desde un restaurante"
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
                        <ul className="space-y-3">
                            {cart.lines.map((line) => {
                                const extrasTotal = line.extras.reduce(
                                    (sum, extra) => sum + extra.price,
                                    0,
                                );
                                const lineTotal =
                                    (line.unitPrice + extrasTotal) *
                                    line.quantity;

                                return (
                                    <li
                                        key={line.key}
                                        className="rounded-xl border border-border bg-surface p-4"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="font-semibold text-navy">
                                                    {line.name}
                                                </p>
                                                {line.extras.length > 0 ? (
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        Extras:{' '}
                                                        {line.extras
                                                            .map(
                                                                (extra) =>
                                                                    extra.name,
                                                            )
                                                            .join(', ')}
                                                    </p>
                                                ) : null}
                                                {line.note ? (
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        Nota: {line.note}
                                                    </p>
                                                ) : null}
                                            </div>
                                            <p className="font-semibold text-navy">
                                                {formatMoney(lineTotal)}
                                            </p>
                                        </div>
                                        <div className="mt-3 flex items-center gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="min-h-11 min-w-11"
                                                onClick={() =>
                                                    updateQuantity(
                                                        line.key,
                                                        line.quantity - 1,
                                                    )
                                                }
                                            >
                                                -
                                            </Button>
                                            <span className="w-8 text-center font-medium">
                                                {line.quantity}
                                            </span>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="min-h-11 min-w-11"
                                                onClick={() =>
                                                    updateQuantity(
                                                        line.key,
                                                        line.quantity + 1,
                                                    )
                                                }
                                            >
                                                +
                                            </Button>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>

                        <OrderSummary
                            subtotal={subtotal}
                            service={service}
                            discount={discount}
                            total={total}
                        />

                        <Button
                            asChild
                            className="min-h-12 w-full"
                            onClick={continueCheckout}
                        >
                            <Link
                                href={
                                    isCustomer
                                        ? customer.checkout()
                                        : login()
                                }
                            >
                                Continuar pedido
                            </Link>
                        </Button>
                    </>
                )}
            </PageContainer>
        </>
    );
}
