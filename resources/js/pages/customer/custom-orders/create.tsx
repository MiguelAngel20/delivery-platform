import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle2, MapPin, ShoppingBag, Store } from 'lucide-react';
import { useMemo, useState } from 'react';
import { AddressCard } from '@/apps/storefront/components/address-card';
import {
    CheckoutStepper,
    CUSTOM_ORDER_STEPS,
} from '@/apps/storefront/components/checkout-stepper';
import { useStorefrontShell } from '@/apps/storefront/hooks/use-storefront-shell';
import { FormField } from '@/components/forms/form-field';
import InputError from '@/components/input-error';
import { PageContainer } from '@/components/layout/page';
import { AddressPicker } from '@/components/maps/address-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { AddressValue } from '@/lib/maps/types';
import { cn } from '@/lib/utils';
import { index, store } from '@/routes/customer/custom-orders';

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
};

type WizardStep = 1 | 2 | 3;
type MerchantMode = 'text' | 'map';

function isTemporaryAddressReady(temporary: Partial<AddressValue>): boolean {
    return Boolean(
        temporary.address_text?.trim() &&
            temporary.latitude &&
            temporary.longitude,
    );
}

export default function CustomerCustomOrderCreate({ addresses }: Props) {
    const { errors } = usePage().props as {
        errors: Record<string, string>;
    };
    const { showBottomNav } = useStorefrontShell();

    const [step, setStep] = useState<WizardStep>(1);
    const [processing, setProcessing] = useState(false);
    const [localError, setLocalError] = useState<string | null>(null);

    const [establishmentName, setEstablishmentName] = useState('');
    const [description, setDescription] = useState('');
    const [customerNotes, setCustomerNotes] = useState('');
    const [merchantMode, setMerchantMode] = useState<MerchantMode>('text');
    const [merchantText, setMerchantText] = useState('');
    const [merchantPlace, setMerchantPlace] = useState<Partial<AddressValue>>(
        {},
    );

    const [deliveryMode, setDeliveryMode] = useState<'saved' | 'temporary'>(
        addresses.length > 0 ? 'saved' : 'temporary',
    );
    const [addressId, setAddressId] = useState(
        addresses.find((address) => address.isDefault)?.id ?? addresses[0]?.id,
    );
    const [temporary, setTemporary] = useState<Partial<AddressValue>>({});

    const step1Ready = description.trim().length > 0;
    const addressReady =
        deliveryMode === 'saved'
            ? Boolean(addressId)
            : isTemporaryAddressReady(temporary);

    const selectedDelivery = useMemo(() => {
        if (deliveryMode === 'saved') {
            return addresses.find((address) => address.id === addressId);
        }

        return {
            label: 'Otra ubicación',
            line:
                temporary.formatted_address ??
                temporary.address_text ??
                '—',
            address_text: temporary.address_text ?? '',
            reference: temporary.reference ?? null,
        };
    }, [deliveryMode, addresses, addressId, temporary]);

    const merchantSummary = useMemo(() => {
        if (merchantMode === 'map' && merchantPlace.address_text?.trim()) {
            return {
                title: establishmentName.trim() || 'Negocio en mapa',
                detail:
                    merchantPlace.formatted_address ??
                    merchantPlace.address_text,
                reference: merchantPlace.reference ?? null,
            };
        }

        if (merchantText.trim() || establishmentName.trim()) {
            return {
                title: establishmentName.trim() || 'Negocio indicado',
                detail: merchantText.trim() || 'Sin dirección detallada',
                reference: null as string | null,
            };
        }

        return null;
    }, [merchantMode, merchantPlace, merchantText, establishmentName]);

    const goNext = () => {
        setLocalError(null);

        if (step === 1) {
            if (!step1Ready) {
                setLocalError('Cuéntanos qué necesitas para continuar.');

                return;
            }

            setStep(2);

            return;
        }

        if (step === 2) {
            if (!addressReady) {
                setLocalError(
                    deliveryMode === 'saved'
                        ? 'Selecciona una dirección guardada.'
                        : 'Busca o marca tu ubicación en el mapa.',
                );

                return;
            }

            setStep(3);
        }
    };

    const goBack = () => {
        setLocalError(null);

        if (step === 1) {
            return;
        }

        setStep((current) => (current - 1) as WizardStep);
    };

    const submit = () => {
        setLocalError(null);

        if (!step1Ready || !addressReady) {
            setLocalError('Revisa los datos del pedido antes de enviar.');

            return;
        }

        const merchantPayload =
            merchantMode === 'map' && isTemporaryAddressReady(merchantPlace)
                ? {
                      merchant_address: merchantPlace.address_text ?? '',
                      merchant_formatted_address:
                          merchantPlace.formatted_address ?? '',
                      merchant_reference: merchantPlace.reference ?? '',
                      merchant_latitude: merchantPlace.latitude ?? '',
                      merchant_longitude: merchantPlace.longitude ?? '',
                      merchant_place_id: merchantPlace.place_id ?? '',
                  }
                : {
                      merchant_address: merchantText.trim() || null,
                      merchant_formatted_address: null,
                      merchant_reference: null,
                      merchant_latitude: null,
                      merchant_longitude: null,
                      merchant_place_id: null,
                  };

        const payload: Record<string, unknown> = {
            establishment_name: establishmentName.trim() || null,
            description: description.trim(),
            customer_notes: customerNotes.trim() || null,
            ...merchantPayload,
            delivery:
                deliveryMode === 'saved'
                    ? {
                          source: 'saved_address',
                          customer_address_id: Number(addressId),
                      }
                    : {
                          source: 'temporary',
                          address_text: temporary.address_text ?? '',
                          formatted_address: temporary.formatted_address ?? '',
                          reference: temporary.reference ?? '',
                          latitude: temporary.latitude ?? '',
                          longitude: temporary.longitude ?? '',
                          place_id: temporary.place_id ?? '',
                          google_maps_url: temporary.google_maps_url ?? '',
                      },
        };

        setProcessing(true);
        router.post(store.url(), payload, {
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <>
            <Head title="Pedido personalizado" />
            <PageContainer className="gap-5 px-4 py-4 pb-28 md:px-6 md:pb-8">
                <div>
                    <h1 className="text-2xl font-semibold text-navy">
                        Pedido personalizado
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        En 3 pasos te ayudamos a cotizar lo que necesitas.
                    </p>
                </div>

                <CheckoutStepper
                    currentStep={step}
                    steps={CUSTOM_ORDER_STEPS}
                />

                {step === 1 ? (
                    <section className="space-y-4">
                        <div className="flex items-center gap-2 text-navy">
                            <ShoppingBag className="size-5 text-primary" />
                            <h2 className="font-semibold">¿Qué necesitas?</h2>
                        </div>

                        <FormField
                            label="¿Qué necesitas?"
                            htmlFor="description"
                            required
                        >
                            <Textarea
                                id="description"
                                value={description}
                                onChange={(event) =>
                                    setDescription(event.target.value)
                                }
                                required
                                rows={5}
                                placeholder="2 frappés grandes de moka y 1 crepa de Nutella"
                            />
                            <InputError message={errors.description} />
                        </FormField>

                        <FormField label="Notas" htmlFor="customer_notes">
                            <Textarea
                                id="customer_notes"
                                value={customerNotes}
                                onChange={(event) =>
                                    setCustomerNotes(event.target.value)
                                }
                                rows={3}
                                placeholder="Sin crema batida, urgencia, etc."
                            />
                            <InputError message={errors.customer_notes} />
                        </FormField>

                        <div className="flex items-center gap-2 pt-2 text-navy">
                            <Store className="size-5 text-primary" />
                            <h2 className="font-semibold">
                                ¿Dónde queda el negocio?
                            </h2>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Opcional: indícalo con texto o márcalo en el mapa
                            para facilitar la recolección.
                        </p>

                        <FormField
                            label="Nombre del establecimiento"
                            htmlFor="establishment_name"
                        >
                            <Input
                                id="establishment_name"
                                value={establishmentName}
                                onChange={(event) =>
                                    setEstablishmentName(event.target.value)
                                }
                                placeholder="Ej. Cafetería Central"
                            />
                            <InputError message={errors.establishment_name} />
                        </FormField>

                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant={
                                    merchantMode === 'text'
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() => setMerchantMode('text')}
                            >
                                Indicar con texto
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant={
                                    merchantMode === 'map'
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() => setMerchantMode('map')}
                            >
                                Marcar en mapa
                            </Button>
                        </div>

                        {merchantMode === 'text' ? (
                            <FormField
                                label="¿Dónde queda?"
                                htmlFor="merchant_address"
                            >
                                <Textarea
                                    id="merchant_address"
                                    value={merchantText}
                                    onChange={(event) =>
                                        setMerchantText(event.target.value)
                                    }
                                    rows={3}
                                    placeholder="Ej. En el mercado municipal, local 12, frente a la fuente"
                                />
                                <InputError message={errors.merchant_address} />
                            </FormField>
                        ) : (
                            <div className="rounded-2xl border border-border bg-surface p-4">
                                <AddressPicker
                                    value={merchantPlace}
                                    showCurrentLocation
                                    showReference
                                    mapHeightClassName="h-64 sm:h-72 md:h-80"
                                    onChange={setMerchantPlace}
                                />
                                <InputError
                                    message={
                                        errors.merchant_latitude ??
                                        errors.merchant_address
                                    }
                                />
                            </div>
                        )}
                    </section>
                ) : null}

                {step === 2 ? (
                    <section className="space-y-4">
                        <div className="flex items-center gap-2 text-navy">
                            <MapPin className="size-5 text-primary" />
                            <h2 className="font-semibold">
                                ¿Dónde te lo entregamos?
                            </h2>
                        </div>

                        {addresses.length > 0 ? (
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant={
                                        deliveryMode === 'saved'
                                            ? 'default'
                                            : 'outline'
                                    }
                                    size="sm"
                                    onClick={() => setDeliveryMode('saved')}
                                >
                                    Dirección guardada
                                </Button>
                                <Button
                                    type="button"
                                    variant={
                                        deliveryMode === 'temporary'
                                            ? 'default'
                                            : 'outline'
                                    }
                                    size="sm"
                                    onClick={() => setDeliveryMode('temporary')}
                                >
                                    Otra ubicación
                                </Button>
                            </div>
                        ) : null}

                        {deliveryMode === 'saved' ? (
                            <div className="space-y-2">
                                {addresses.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No tienes direcciones guardadas. Usa otra
                                        ubicación.
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
                                    showReference
                                    mapHeightClassName="h-72 sm:h-80 md:h-96"
                                    onChange={(value) => {
                                        setTemporary(value);
                                        setLocalError(null);
                                    }}
                                />
                            </div>
                        )}
                        <InputError
                            message={
                                errors['delivery.address_text'] ??
                                errors['delivery.latitude'] ??
                                errors['delivery.longitude'] ??
                                errors.delivery
                            }
                        />
                    </section>
                ) : null}

                {step === 3 ? (
                    <section className="space-y-4">
                        <div className="flex items-center gap-2 text-navy">
                            <CheckCircle2 className="size-5 text-primary" />
                            <h2 className="font-semibold">
                                Confirma tu solicitud
                            </h2>
                        </div>

                        <div className="rounded-2xl border border-border bg-surface p-4">
                            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Pedido
                            </p>
                            <p className="mt-1 whitespace-pre-wrap font-semibold text-navy">
                                {description}
                            </p>
                            {customerNotes.trim() ? (
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Notas: {customerNotes}
                                </p>
                            ) : null}
                        </div>

                        <div className="rounded-2xl border border-border bg-surface p-4">
                            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Negocio / recolección
                            </p>
                            {merchantSummary ? (
                                <>
                                    <p className="mt-1 font-semibold text-navy">
                                        {merchantSummary.title}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {merchantSummary.detail}
                                    </p>
                                    {merchantSummary.reference ? (
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Ref: {merchantSummary.reference}
                                        </p>
                                    ) : null}
                                </>
                            ) : (
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Sin ubicación de negocio indicada. ChisDrive
                                    te contactará si hace falta.
                                </p>
                            )}
                        </div>

                        <div className="rounded-2xl border border-border bg-surface p-4">
                            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Entrega
                            </p>
                            <p className="mt-1 font-semibold text-navy">
                                {selectedDelivery?.label}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {selectedDelivery?.line ??
                                    selectedDelivery?.address_text}
                            </p>
                            {selectedDelivery?.reference ? (
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Ref: {selectedDelivery.reference}
                                </p>
                            ) : null}
                        </div>
                    </section>
                ) : null}

                <InputError message={localError} />

                <div
                    className={cn(
                        'border-t border-border bg-background/95 backdrop-blur',
                        'fixed inset-x-0 z-20',
                        showBottomNav ? 'bottom-16' : 'bottom-0',
                        'md:static md:z-auto md:rounded-xl md:border md:bg-surface md:shadow-sm md:backdrop-blur-none',
                    )}
                >
                    <div className="mx-auto flex w-full max-w-6xl gap-2.5 px-4 py-3 md:px-5 md:py-4">
                        {step > 1 ? (
                            <Button
                                type="button"
                                variant="outline"
                                className="min-h-11 flex-1 md:min-h-12 md:flex-none md:px-6"
                                onClick={goBack}
                                disabled={processing}
                            >
                                Atrás
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                variant="outline"
                                className="min-h-11 flex-1 md:min-h-12 md:flex-none md:px-6"
                                asChild
                            >
                                <Link href={index.url()}>Cancelar</Link>
                            </Button>
                        )}
                        <Button
                            type="button"
                            className="min-h-11 flex-[2] md:min-h-12 md:flex-none md:px-8"
                            onClick={step === 3 ? submit : goNext}
                            disabled={
                                processing ||
                                (step === 1 && !step1Ready) ||
                                (step === 2 && !addressReady)
                            }
                            loading={processing}
                        >
                            {step === 3 ? 'Enviar solicitud' : 'Continuar'}
                        </Button>
                    </div>
                </div>
            </PageContainer>
        </>
    );
}
