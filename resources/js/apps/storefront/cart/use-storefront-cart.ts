import { usePage } from '@inertiajs/react';
import { useCallback, useMemo, useSyncExternalStore } from 'react';
import type { SelectedProductOption } from '@/apps/storefront/components/product-dialog';

const STORAGE_KEY = 'ride.storefront.cart';
const PENDING_CLEAR_KEY = 'ride.storefront.cart.pending_clear';
const listeners = new Set<() => void>();

export type CartExtra = {
    id: string;
    name: string;
    price: number;
};

export type PromotionCartItemSelection = {
    promotionItemId: number;
    name: string;
    selectedOptions: SelectedProductOption[];
    note?: string;
};

type BaseCartLine = {
    key: string;
    branchId: number;
    restaurantSlug: string;
    restaurantName: string;
    name: string;
    unitPrice: number;
    quantity: number;
};

export type ProductCartLine = BaseCartLine & {
    lineType?: 'product';
    productId: string;
    extras: CartExtra[];
    note?: string;
    removedIngredients?: string[];
    selectedOptions?: SelectedProductOption[];
};

export type PromotionCartLine = BaseCartLine & {
    lineType: 'promotion';
    promotionId: string;
    composition?: string;
    promotionItems: PromotionCartItemSelection[];
    note?: string;
};

export type CartLine = ProductCartLine | PromotionCartLine;

export function isPromotionCartLine(line: CartLine): line is PromotionCartLine {
    return line.lineType === 'promotion';
}

export type CartState = {
    branchId: number | null;
    restaurantSlug: string | null;
    restaurantName: string | null;
    restaurantMode?: string | null;
    lines: CartLine[];
};

const emptyCart: CartState = {
    branchId: null,
    restaurantSlug: null,
    restaurantName: null,
    restaurantMode: null,
    lines: [],
};

let cachedRaw: string | null = null;
let cachedCart: CartState = emptyCart;

function emit(): void {
    listeners.forEach((listener) => listener());
}

function readCart(): CartState {
    if (typeof window === 'undefined') {
        return emptyCart;
    }

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);

        if (raw === cachedRaw) {
            return cachedCart;
        }

        cachedRaw = raw;

        if (!raw) {
            cachedCart = emptyCart;

            return cachedCart;
        }

        const parsed = JSON.parse(raw) as CartState;

        if (!parsed || !Array.isArray(parsed.lines)) {
            cachedCart = emptyCart;

            return cachedCart;
        }

        cachedCart = {
            ...parsed,
            lines: parsed.lines.map((line) => ({
                lineType: line.lineType ?? 'product',
                ...line,
            })),
        };

        return cachedCart;
    } catch {
        cachedRaw = null;
        cachedCart = emptyCart;

        return emptyCart;
    }
}

function writeCart(next: CartState): void {
    const raw = JSON.stringify(next);
    window.localStorage.setItem(STORAGE_KEY, raw);
    cachedRaw = raw;
    cachedCart = next;
    emit();
}

/** Mark cart for cleanup if Inertia finishes via full-page reload (asset version mismatch). */
export function markCartPendingClear(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.sessionStorage.setItem(PENDING_CLEAR_KEY, '1');
}

export function clearStorefrontCart(): void {
    if (typeof window === 'undefined') {
        return;
    }

    writeCart(emptyCart);
    window.sessionStorage.removeItem(PENDING_CLEAR_KEY);
}

/** Call on app/pages that load after checkout redirect. */
export function consumePendingCartClear(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    if (window.sessionStorage.getItem(PENDING_CLEAR_KEY) !== '1') {
        return false;
    }

    clearStorefrontCart();

    return true;
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

function promotionLineKey(
    promotionId: string,
    promotionItems: PromotionCartItemSelection[],
    note?: string,
): string {
    const itemSignature = promotionItems
        .map((item) => {
            const options = (item.selectedOptions ?? [])
                .map((option) => `${option.option_id}:${option.action}`)
                .sort()
                .join(',');

            return `${item.promotionItemId}|${options}|${item.note ?? ''}`;
        })
        .join(';');

    return ['promotion', promotionId, itemSignature, note ?? ''].join('|');
}

function lineKey(
    productId: string,
    extras: CartExtra[],
    note?: string,
    removedIngredients?: string[],
    selectedOptions?: SelectedProductOption[],
): string {
    return [
        productId,
        extras
            .map((extra) => extra.id)
            .sort()
            .join(','),
        note ?? '',
        (removedIngredients ?? []).slice().sort().join(','),
        (selectedOptions ?? [])
            .map((option) => `${option.option_id}:${option.action}`)
            .sort()
            .join(','),
    ].join('|');
}

export type AddToCartProduct = {
    id: number | string;
    branchId: number;
    restaurantSlug: string;
    restaurantName: string;
    restaurantMode?: string;
    name: string;
    price: number;
};

export type AddToCartPromotion = {
    id: number | string;
    branchId: number;
    restaurantSlug: string;
    restaurantName: string;
    restaurantMode?: string;
    name: string;
    price: number;
    composition?: string;
};

export type AddPromotionToCartInput = {
    promotion: AddToCartPromotion;
    quantity: number;
    promotionItems: PromotionCartItemSelection[];
    note?: string;
};

export type AddToCartInput = {
    product: AddToCartProduct;
    quantity: number;
    extras: CartExtra[];
    note?: string;
    removedIngredients?: string[];
    selectedOptions?: SelectedProductOption[];
};

export function useStorefrontCart() {
    const page = usePage<{
        orderSettings?: { service_fee?: number };
    }>();
    const cart = useSyncExternalStore(subscribe, readCart, () => emptyCart);

    const itemCount = useMemo(
        () => cart.lines.reduce((sum, line) => sum + line.quantity, 0),
        [cart.lines],
    );

    const subtotal = useMemo(
        () =>
            cart.lines.reduce((sum, line) => {
                if (isPromotionCartLine(line)) {
                    return sum + line.unitPrice * line.quantity;
                }

                const extrasTotal = line.extras.reduce(
                    (extraSum, extra) => extraSum + extra.price,
                    0,
                );

                return sum + (line.unitPrice + extrasTotal) * line.quantity;
            }, 0),
        [cart.lines],
    );

    const discount =
        cart.restaurantMode === 'platform_operated' && subtotal > 0 ? 5 : 0;
    const service =
        cart.lines.length > 0 ? (page.props.orderSettings?.service_fee ?? 50) : 0;
    const total = Math.max(subtotal + service - discount, 0);

    const clear = useCallback(() => {
        clearStorefrontCart();
    }, []);

    const addItem = useCallback((input: AddToCartInput): 'ok' | 'conflict' => {
        const current = readCart();
        const branchId = input.product.branchId;

        if (
            current.branchId &&
            current.branchId !== branchId &&
            current.lines.length > 0
        ) {
            return 'conflict';
        }

        const key = lineKey(
            String(input.product.id),
            input.extras,
            input.note,
            input.removedIngredients,
            input.selectedOptions,
        );

        const existing = current.lines.find((line) => line.key === key);
        const lines = existing
            ? current.lines.map((line) =>
                  line.key === key
                      ? {
                            ...line,
                            quantity: line.quantity + input.quantity,
                        }
                      : line,
              )
            : [
                  ...current.lines,
                  {
                      key: lineKey(
                          String(input.product.id),
                          input.extras,
                          input.note,
                          input.removedIngredients,
                          input.selectedOptions,
                      ),
                      lineType: 'product',
                      productId: String(input.product.id),
                      branchId,
                      restaurantSlug: input.product.restaurantSlug,
                      restaurantName: input.product.restaurantName,
                      name: input.product.name,
                      unitPrice: input.product.price,
                      quantity: input.quantity,
                      extras: input.extras,
                      note: input.note,
                      removedIngredients: input.removedIngredients,
                      selectedOptions: input.selectedOptions,
                  },
              ];

        writeCart({
            branchId,
            restaurantSlug: input.product.restaurantSlug,
            restaurantName: input.product.restaurantName,
            restaurantMode: input.product.restaurantMode ?? null,
            lines,
        });

        return 'ok';
    }, []);

    const addPromotion = useCallback(
        (input: AddPromotionToCartInput): 'ok' | 'conflict' => {
            const current = readCart();
            const branchId = input.promotion.branchId;

            if (
                current.branchId &&
                current.branchId !== branchId &&
                current.lines.length > 0
            ) {
                return 'conflict';
            }

            const key = promotionLineKey(
                String(input.promotion.id),
                input.promotionItems,
                input.note,
            );

            const existing = current.lines.find((line) => line.key === key);
            const lines = existing
                ? current.lines.map((line) =>
                      line.key === key
                          ? {
                                ...line,
                                quantity: line.quantity + input.quantity,
                            }
                          : line,
                  )
                : [
                      ...current.lines,
                      {
                          key,
                          lineType: 'promotion' as const,
                          promotionId: String(input.promotion.id),
                          branchId,
                          restaurantSlug: input.promotion.restaurantSlug,
                          restaurantName: input.promotion.restaurantName,
                          name: input.promotion.name,
                          unitPrice: input.promotion.price,
                          quantity: input.quantity,
                          composition: input.promotion.composition,
                          promotionItems: input.promotionItems,
                          note: input.note,
                      },
                  ];

            writeCart({
                branchId,
                restaurantSlug: input.promotion.restaurantSlug,
                restaurantName: input.promotion.restaurantName,
                restaurantMode: input.promotion.restaurantMode ?? null,
                lines,
            });

            return 'ok';
        },
        [],
    );

    const replaceWithPromotion = useCallback((input: AddPromotionToCartInput) => {
        writeCart({
            branchId: input.promotion.branchId,
            restaurantSlug: input.promotion.restaurantSlug,
            restaurantName: input.promotion.restaurantName,
            restaurantMode: input.promotion.restaurantMode ?? null,
            lines: [
                {
                    key: promotionLineKey(
                        String(input.promotion.id),
                        input.promotionItems,
                        input.note,
                    ),
                    lineType: 'promotion',
                    promotionId: String(input.promotion.id),
                    branchId: input.promotion.branchId,
                    restaurantSlug: input.promotion.restaurantSlug,
                    restaurantName: input.promotion.restaurantName,
                    name: input.promotion.name,
                    unitPrice: input.promotion.price,
                    quantity: input.quantity,
                    composition: input.promotion.composition,
                    promotionItems: input.promotionItems,
                    note: input.note,
                },
            ],
        });
    }, []);

    const replaceWithItem = useCallback((input: AddToCartInput) => {
        writeCart({
            branchId: input.product.branchId,
            restaurantSlug: input.product.restaurantSlug,
            restaurantName: input.product.restaurantName,
            restaurantMode: input.product.restaurantMode ?? null,
            lines: [
                {
                    key: lineKey(
                        String(input.product.id),
                        input.extras,
                        input.note,
                        input.removedIngredients,
                        input.selectedOptions,
                    ),
                    lineType: 'product',
                    productId: String(input.product.id),
                    branchId: input.product.branchId,
                    restaurantSlug: input.product.restaurantSlug,
                    restaurantName: input.product.restaurantName,
                    name: input.product.name,
                    unitPrice: input.product.price,
                    quantity: input.quantity,
                    extras: input.extras,
                    note: input.note,
                    removedIngredients: input.removedIngredients,
                    selectedOptions: input.selectedOptions,
                },
            ],
        });
    }, []);

    const updateQuantity = useCallback((key: string, quantity: number) => {
        const current = readCart();

        if (quantity <= 0) {
            const lines = current.lines.filter((line) => line.key !== key);
            writeCart(lines.length === 0 ? emptyCart : { ...current, lines });

            return;
        }

        writeCart({
            ...current,
            lines: current.lines.map((line) =>
                line.key === key ? { ...line, quantity } : line,
            ),
        });
    }, []);

    const replaceLine = useCallback(
        (oldKey: string, input: AddToCartInput | AddPromotionToCartInput) => {
            const current = readCart();
            const filtered = current.lines.filter((line) => line.key !== oldKey);

            if ('promotionItems' in input) {
                const key = promotionLineKey(
                    String(input.promotion.id),
                    input.promotionItems,
                    input.note,
                );
                const existingIndex = filtered.findIndex(
                    (line) => line.key === key,
                );

                if (existingIndex !== -1) {
                    writeCart({
                        ...current,
                        lines: filtered.map((line, index) =>
                            index === existingIndex
                                ? {
                                      ...line,
                                      quantity: line.quantity + input.quantity,
                                  }
                                : line,
                        ),
                    });

                    return;
                }

                writeCart({
                    ...current,
                    lines: [
                        ...filtered,
                        {
                            key,
                            lineType: 'promotion',
                            promotionId: String(input.promotion.id),
                            branchId: input.promotion.branchId,
                            restaurantSlug: input.promotion.restaurantSlug,
                            restaurantName: input.promotion.restaurantName,
                            name: input.promotion.name,
                            unitPrice: input.promotion.price,
                            quantity: input.quantity,
                            composition: input.promotion.composition,
                            promotionItems: input.promotionItems,
                            note: input.note,
                        },
                    ],
                });

                return;
            }

            const key = lineKey(
            String(input.product.id),
            input.extras,
            input.note,
            input.removedIngredients,
            input.selectedOptions,
        );

        const existingIndex = filtered.findIndex((line) => line.key === key);

        if (existingIndex !== -1) {
            writeCart({
                ...current,
                lines: filtered.map((line, index) =>
                    index === existingIndex
                        ? {
                              ...line,
                              quantity: line.quantity + input.quantity,
                          }
                        : line,
                ),
            });

            return;
        }

        writeCart({
            ...current,
            lines: [
                ...filtered,
                {
                    key,
                    lineType: 'product',
                    productId: String(input.product.id),
                    branchId: input.product.branchId,
                    restaurantSlug: input.product.restaurantSlug,
                    restaurantName: input.product.restaurantName,
                    name: input.product.name,
                    unitPrice: input.product.price,
                    quantity: input.quantity,
                    extras: input.extras,
                    note: input.note,
                    removedIngredients: input.removedIngredients,
                    selectedOptions: input.selectedOptions,
                },
            ],
        });
        },
        [],
    );

    return {
        cart,
        itemCount,
        subtotal,
        service,
        discount,
        total,
        addItem,
        addPromotion,
        replaceWithItem,
        replaceWithPromotion,
        replaceLine,
        updateQuantity,
        clear,
    } as const;
}

export function setCheckoutIntent(path: string): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.sessionStorage.setItem('ride.checkout.intent', path);
}

export function consumeCheckoutIntent(): string | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const value = window.sessionStorage.getItem('ride.checkout.intent');
    window.sessionStorage.removeItem('ride.checkout.intent');

    return value;
}
