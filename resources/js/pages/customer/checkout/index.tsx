import { Head, Link, router, usePage } from '@inertiajs/react';
import { Banknote, CheckCircle2, MapPin } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    markCartPendingClear,
    useStorefrontCart,
} from '@/apps/storefront/cart/use-storefront-cart';
import { AddressCard } from '@/apps/storefront/components/address-card';
import { CartLineCard } from '@/apps/storefront/components/cart-line-card';
import { CheckoutFooter } from '@/apps/storefront/components/checkout-footer';
import { CheckoutStepper } from '@/apps/storefront/components/checkout-stepper';
import { OrderSummary } from '@/apps/storefront/components/order-summary';
import { notify } from '@/components/feedback/toast';
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

type CheckoutWizardStep = 2 | 3 | 4;

function isAddressValid(
    mode: 'saved' | 'temporary',
    addressId: string | undefined,
    temporary: Partial<AddressValue>,
    addressesCount: number,
): boolean {
    if (mode === 'saved') {
        return addressesCount > 0 && Boolean(addressId);
    }

    return Boolean(
        temporary.address_text?.trim() &&
            temporary.latitude &&
            temporary.longitude,
    );
}

export default function CustomerCheckout({ addresses }: Props) {
    const { cart: bag, subtotal, service, discount, total, clear } =
        useStorefrontCart();
    const pageErrors = usePage().props.errors ?? {};
    const [step, setStep] = useState<CheckoutWizardStep>(2);
    const [processing, setProcessing] = useState(false);
    const [mode, setMode] = useState<'saved' | 'temporary'>(
        addresses.length > 0 ? 'saved' : 'temporary',
    );
    const [addressId, setAddressId] = useState(
        addresses.find((address) => address.isDefault)?.id ?? addresses[0]?.id,
    );
    const [temporary, setTemporary] = useState<Partial<AddressValue>>({});
    const [paymentMethod] = useState<'cash'>('cash');

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

    const selectedAddress = useMemo(() => {
        if (mode === 'saved') {
            return addresses.find((address) => address.id === addressId);
        }

        return {
            label: 'Ubicación temporal',
            line:
                temporary.formatted_address ??
                temporary.address_text ??
                'Sin dirección',
            address_text: temporary.address_text ?? '',
            reference: temporary.reference ?? null,
        };
    }, [mode, addresses, addressId, temporary]);

    const errorMessages = Object.values(pageErrors ?? {}).flatMap(
        (message) => (typeof message === 'string' ? [message] : []),
    );

    const addressReady = isAddressValid(
        mode,
        addressId,
        temporary,
        addresses.length,
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

    const buildDelivery = () => {
        if (mode === 'saved') {
            return {
                source: 'saved_address' as const,
                customer_address_id: Number(addressId),
                address_text: '',
                reference: '',
                latitude: '',
                longitude: '',
                google_maps_url: '',
            };
        }

        return {
            source: 'temporary' as const,
            customer_address_id: null,
            address_text: temporary.address_text ?? '',
            formatted_address: temporary.formatted_address ?? '',
            reference: temporary.reference ?? '',
            latitude: temporary.latitude ?? '',
            longitude: temporary.longitude ?? '',
            place_id: temporary.place_id ?? '',
            google_maps_url: temporary.google_maps_url ?? '',
        };
    };

    const submit = () => {
        if (!bag.branchId || processing || !addressReady) {
            return;
        }

        router.post(
            store.url(),
            {
                branch_id: bag.branchId,
                notes: null,
                items: payloadItems,
                delivery: buildDelivery(),
            },
            {
                preserveScroll: true,
                onStart: () => {
                    setProcessing(true);
                    markCartPendingClear();
                },
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    clear();
                    notify.success('Pedido creado correctamente.');
                },
                onError: () => {
                    window.sessionStorage.removeItem(
                        'ride.storefront.cart.pending_clear',
                    );
                    notify.error('No se pudo crear el pedido. Revisa los datos.');
                },
                onCancel: () => {
                    window.sessionStorage.removeItem(
                        'ride.storefront.cart.pending_clear',
                    );
                },
            },
        );
    };

    const goNext = () => {
        if (step === 2) {
            if (!addressReady) {
                notify.error('Selecciona o ingresa una dirección de entrega.');

                return;
            }

            setStep(3);

            return;
        }

        if (step === 3) {
            setStep(4);
        }
    };

    const goBack = () => {
        if (step === 2) {
            router.visit(cartRoute());

            return;
        }

        setStep((current) => (current - 1) as CheckoutWizardStep);
    };

    return (
        <>
            <Head title="Checkout" />
            <PageContainer className="gap-5 px-4 py-4 pb-28 md:px-6 md:pb-32">
                <CheckoutStepper currentStep={step} />

                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-navy">
                        {step === 2
                            ? 'Dirección de entrega'
                            : step === 3
                              ? 'Método de pago'
                              : 'Confirmar pedido'}
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

                {step === 2 ? (
                    <section className="space-y-4">
                        <div className="flex items-center gap-2 text-navy">
                            <MapPin className="size-5 text-primary" />
                            <h2 className="font-semibold">
                                ¿Dónde entregamos tu pedido?
                            </h2>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant={mode === 'saved' ? 'default' : 'outline'}
                                disabled={addresses.length === 0}
                                onClick={() => setMode('saved')}
                            >
                                Dirección guardada
                            </Button>
                            <Button
                                type="button"
                                variant={
                                    mode === 'temporary' ? 'default' : 'outline'
                                }
                                onClick={() => setMode('temporary')}
                            >
                                Otra ubicación
                            </Button>
                        </div>

                        {mode === 'saved' ? (
                            <div className="space-y-3">
                                {addresses.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No tienes direcciones guardadas. Usa otra
                                        ubicación o agrega una en tu perfil.
                                    </p>
                                ) : (
                                    addresses.map((address) => (
                                        <AddressCard
                                            key={address.id}
                                            address={address}
                                            selected={addressId === address.id}
                                            onSelect={() =>
                                                setAddressId(address.id)
                                            }
                                        />
                                    ))
                                )}
                            </div>
                        ) : (
                            <div className="rounded-2xl border border-border bg-surface p-4">
                                <AddressPicker
                                    value={temporary}
                                    showCurrentLocation
                                    mapHeightClassName="h-72 sm:h-80 md:h-96"
                                    onChange={(value) => setTemporary(value)}
                                />
                            </div>
                        )}
                    </section>
                ) : null}

                {step === 3 ? (
                    <section className="space-y-4">
                        <div className="flex items-center gap-2 text-navy">
                            <Banknote className="size-5 text-primary" />
                            <h2 className="font-semibold">
                                ¿Cómo vas a pagar?
                            </h2>
                        </div>

                        <div className="space-y-3">
                            <button
                                type="button"
                                className="w-full rounded-2xl border-2 border-primary bg-primary/5 p-4 text-left shadow-sm"
                            >
                                <div className="flex items-start gap-3">
                                    <div className="flex size-10 items-center justify-center rounded-full bg-primary/10">
                                        <Banknote className="size-5 text-primary" />
                                    </div>
                                    <div>
                                        <Label className="text-base font-semibold text-navy">
                                            Efectivo
                                        </Label>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Paga al recibir tu pedido. Único
                                            método disponible por ahora.
                                        </p>
                                    </div>
                                </div>
                            </button>
                        </div>

                        <p className="text-xs text-muted-foreground">
                            Método seleccionado:{' '}
                            {paymentMethod === 'cash' ? 'Efectivo' : paymentMethod}
                        </p>
                    </section>
                ) : null}

                {step === 4 ? (
                    <section className="space-y-5">
                        <div className="flex items-center gap-2 text-navy">
                            <CheckCircle2 className="size-5 text-primary" />
                            <h2 className="font-semibold">
                                Revisa antes de finalizar
                            </h2>
                        </div>

                        <div className="rounded-2xl border border-border bg-surface p-4">
                            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Entrega
                            </p>
                            <p className="mt-1 font-semibold text-navy">
                                {selectedAddress?.label}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {selectedAddress?.line}
                            </p>
                            {selectedAddress?.reference ? (
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Ref: {selectedAddress.reference}
                                </p>
                            ) : null}
                        </div>

                        <div className="rounded-2xl border border-border bg-surface p-4">
                            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Pago
                            </p>
                            <p className="mt-1 font-semibold text-navy">
                                Efectivo al recibir
                            </p>
                        </div>

                        <div className="space-y-3">
                            <p className="text-sm font-semibold text-navy">
                                Productos
                            </p>
                            <ul className="space-y-3">
                                {bag.lines.map((line) => (
                                    <li key={line.key}>
                                        <CartLineCard line={line} compact />
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <OrderSummary
                            subtotal={subtotal}
                            service={service}
                            discount={discount}
                            total={total}
                        />
                    </section>
                ) : null}

                <CheckoutFooter
                    total={total}
                    primaryLabel={
                        step === 4 ? 'Finalizar pedido' : 'Continuar'
                    }
                    onPrimary={step === 4 ? submit : goNext}
                    primaryDisabled={
                        step === 2 ? !addressReady : step === 4 ? !bag.branchId : false
                    }
                    primaryLoading={processing}
                    onBack={goBack}
                    backLabel={step === 2 ? 'Editar pedido' : 'Atrás'}
                />
            </PageContainer>
        </>
    );
}
