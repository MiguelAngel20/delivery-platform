import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { useStorefrontCart } from '@/apps/storefront/cart/use-storefront-cart';
import { AddressCard } from '@/apps/storefront/components/address-card';
import { OrderSummary } from '@/apps/storefront/components/order-summary';
import { EmptyState } from '@/components/feedback/empty-state';
import InputError from '@/components/input-error';
import { PageContainer } from '@/components/layout/page';
import { AddressPicker } from '@/components/maps/address-picker';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { AddressValue } from '@/lib/maps/types';
import { cart as cartRoute } from '@/routes';
import { store } from '@/routes/customer/orders';

type Address = {
    id: string;
    label: string;
    line: string;
    address_text: string;
    reference?: string | null;
    latitude: string;
    longitude: string;
    isDefault: boolean;
};

type Props = {
    addresses: Address[];
    orderSettings: {
        service_fee: number;
        delivery_fee: number;
    };
};

export default function CustomerCheckout({ addresses }: Props) {
    const { cart: bag, subtotal, service, discount, total, clear } =
        useStorefrontCart();
    const pageErrors = usePage().props.errors ?? {};
    const [processing, setProcessing] = useState(false);
    const [mode, setMode] = useState<'saved' | 'temporary'>(
        addresses.length > 0 ? 'saved' : 'temporary',
    );
    const [addressId, setAddressId] = useState(
        addresses.find((address) => address.isDefault)?.id ?? addresses[0]?.id,
    );
    const [temporary, setTemporary] = useState<Partial<AddressValue>>({});

    const payloadItems = useMemo(
        () =>
            bag.lines.map((line) => ({
                product_id: Number(line.productId),
                quantity: line.quantity,
                special_instructions: line.note,
                selected_options: (line.selectedOptions ?? []).map((option) => ({
                    option_id: option.option_id,
                    action: option.action,
                })),
            })),
        [bag.lines],
    );

    const errorMessages = Object.values(pageErrors).filter(
        (message): message is string => typeof message === 'string',
    );

    if (bag.lines.length === 0) {
        return (
            <>
                <Head title="Checkout" />
                <PageContainer className="px-4 py-4 md:px-6">
                    <EmptyState
                        title="Tu carrito está vacío"
                        action={
                            <Button asChild>
                                <Link href={cartRoute()}>Ir al carrito</Link>
                            </Button>
                        }
                    />
                </PageContainer>
            </>
        );
    }

    const submit = () => {
        if (!bag.branchId || processing) {
            return;
        }

        const delivery =
            mode === 'saved'
                ? {
                      source: 'saved_address',
                      customer_address_id: Number(addressId),
                      address_text: '',
                      reference: '',
                      latitude: '',
                      longitude: '',
                      google_maps_url: '',
                  }
                : {
                      source: 'temporary',
                      customer_address_id: null,
                      address_text: temporary.address_text ?? '',
                      formatted_address: temporary.formatted_address ?? '',
                      reference: temporary.reference ?? '',
                      latitude: temporary.latitude ?? '',
                      longitude: temporary.longitude ?? '',
                      place_id: temporary.place_id ?? '',
                      google_maps_url: temporary.google_maps_url ?? '',
                  };

        router.post(
            store.url(),
            {
                branch_id: bag.branchId,
                notes: null,
                items: payloadItems,
                delivery,
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => clear(),
            },
        );
    };

    return (
        <>
            <Head title="Checkout" />
            <PageContainer className="gap-5 px-4 py-4 md:px-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-navy">
                        Checkout
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {bag.restaurantName}
                    </p>
                </div>

                {errorMessages.length > 0 ? (
                    <div className="rounded-xl border border-destructive/40 bg-destructive/5 p-4">
                        <p className="mb-2 text-sm font-medium text-destructive">
                            No se pudo crear el pedido
                        </p>
                        <ul className="space-y-1">
                            {errorMessages.map((message) => (
                                <li key={message}>
                                    <InputError message={message} />
                                </li>
                            ))}
                        </ul>
                    </div>
                ) : null}

                <section className="space-y-3">
                    <h2 className="font-semibold text-navy">Dirección</h2>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant={mode === 'saved' ? 'default' : 'outline'}
                            disabled={addresses.length === 0}
                            onClick={() => setMode('saved')}
                        >
                            Guardada
                        </Button>
                        <Button
                            type="button"
                            variant={mode === 'temporary' ? 'default' : 'outline'}
                            onClick={() => setMode('temporary')}
                        >
                            Usar otra ubicación
                        </Button>
                    </div>

                    {mode === 'saved' ? (
                        <div className="space-y-3">
                            {addresses.map((address) => (
                                <AddressCard
                                    key={address.id}
                                    address={address}
                                    selected={addressId === address.id}
                                    onSelect={() => setAddressId(address.id)}
                                />
                            ))}
                        </div>
                    ) : (
                        <div className="rounded-xl border border-border bg-surface p-4">
                            <AddressPicker
                                value={temporary}
                                showCurrentLocation
                                onChange={(value) => setTemporary(value)}
                            />
                        </div>
                    )}
                </section>

                <section className="space-y-3">
                    <h2 className="font-semibold text-navy">Pago</h2>
                    <div className="rounded-xl border border-primary bg-surface p-4">
                        <Label className="text-base font-semibold text-navy">
                            Efectivo
                        </Label>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Único método disponible en V1. El total se confirma
                            en el servidor.
                        </p>
                    </div>
                </section>

                <section className="space-y-3">
                    <h2 className="font-semibold text-navy">Resumen</h2>
                    <ul className="space-y-2 rounded-xl border border-border bg-surface p-4 text-sm">
                        {bag.lines.map((line) => (
                            <li
                                key={line.key}
                                className="flex justify-between gap-3"
                            >
                                <span className="text-navy">
                                    {line.quantity}x {line.name}
                                </span>
                            </li>
                        ))}
                    </ul>
                    <OrderSummary
                        subtotal={subtotal}
                        service={service}
                        discount={discount}
                        total={total}
                    />
                </section>

                <Button
                    type="button"
                    className="min-h-12 w-full"
                    onClick={submit}
                    disabled={processing || !bag.branchId}
                >
                    {processing ? 'Confirmando…' : 'Confirmar pedido'}
                </Button>
            </PageContainer>
        </>
    );
}
