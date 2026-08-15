import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AddressCard } from '@/apps/storefront/components/address-card';
import { FormField } from '@/components/forms/form-field';
import InputError from '@/components/input-error';
import { PageContainer } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { store } from '@/routes/customer/custom-orders';
import { index } from '@/routes/customer/custom-orders';

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

export default function CustomerCustomOrderCreate({ addresses }: Props) {
    const [processing, setProcessing] = useState(false);
    const [mode, setMode] = useState<'saved' | 'temporary'>(
        addresses.length > 0 ? 'saved' : 'temporary',
    );
    const [addressId, setAddressId] = useState(
        addresses.find((address) => address.isDefault)?.id ?? addresses[0]?.id,
    );
    const [temporary, setTemporary] = useState({
        address_text: '',
        reference: '',
        latitude: '',
        longitude: '',
    });

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const payload: Record<string, unknown> = {
            establishment_name: form.get('establishment_name'),
            description: form.get('description'),
            customer_notes: form.get('customer_notes'),
            delivery:
                mode === 'saved'
                    ? {
                          source: 'saved_address',
                          customer_address_id: Number(addressId),
                      }
                    : {
                          source: 'temporary',
                          ...temporary,
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
            <PageContainer className="gap-5 px-4 py-4 md:px-6">
                <div>
                    <h1 className="text-2xl font-semibold text-navy">
                        Pedido personalizado
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Describe lo que necesitas. RIDE te enviará una cotización.
                    </p>
                </div>

                <form className="space-y-4" onSubmit={submit}>
                    <FormField label="Establecimiento" htmlFor="establishment_name">
                        <Input
                            id="establishment_name"
                            name="establishment_name"
                            placeholder="Ej. Cafetería Central"
                        />
                    </FormField>
                    <FormField label="¿Qué necesitas?" htmlFor="description" required>
                        <Textarea
                            id="description"
                            name="description"
                            required
                            rows={5}
                            placeholder="2 frappés grandes de moka y 1 crepa de Nutella"
                        />
                    </FormField>
                    <FormField label="Notas" htmlFor="customer_notes">
                        <Textarea
                            id="customer_notes"
                            name="customer_notes"
                            rows={3}
                            placeholder="Sin crema batida"
                        />
                    </FormField>

                    <section className="space-y-3">
                        <h2 className="text-sm font-semibold text-navy">
                            Dirección de entrega
                        </h2>
                        {addresses.length > 0 ? (
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant={mode === 'saved' ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => setMode('saved')}
                                >
                                    Guardada
                                </Button>
                                <Button
                                    type="button"
                                    variant={mode === 'temporary' ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => setMode('temporary')}
                                >
                                    Temporal
                                </Button>
                            </div>
                        ) : null}

                        {mode === 'saved' ? (
                            <div className="space-y-2">
                                {addresses.map((address) => (
                                    <button
                                        key={address.id}
                                        type="button"
                                        onClick={() => setAddressId(address.id)}
                                        className="w-full text-left"
                                    >
                                        <AddressCard
                                            address={address}
                                            selected={addressId === address.id}
                                        />
                                    </button>
                                ))}
                            </div>
                        ) : (
                            <div className="grid gap-3">
                                <Input
                                    placeholder="Dirección"
                                    value={temporary.address_text}
                                    onChange={(event) =>
                                        setTemporary({
                                            ...temporary,
                                            address_text: event.target.value,
                                        })
                                    }
                                    required={mode === 'temporary'}
                                />
                                <Input
                                    placeholder="Referencia"
                                    value={temporary.reference}
                                    onChange={(event) =>
                                        setTemporary({
                                            ...temporary,
                                            reference: event.target.value,
                                        })
                                    }
                                />
                                <div className="grid grid-cols-2 gap-3">
                                    <Input
                                        placeholder="Latitud"
                                        value={temporary.latitude}
                                        onChange={(event) =>
                                            setTemporary({
                                                ...temporary,
                                                latitude: event.target.value,
                                            })
                                        }
                                        required={mode === 'temporary'}
                                    />
                                    <Input
                                        placeholder="Longitud"
                                        value={temporary.longitude}
                                        onChange={(event) =>
                                            setTemporary({
                                                ...temporary,
                                                longitude: event.target.value,
                                            })
                                        }
                                        required={mode === 'temporary'}
                                    />
                                </div>
                            </div>
                        )}
                    </section>

                    <InputError message={undefined} />
                    <div className="flex gap-3">
                        <Button type="submit" disabled={processing}>
                            Enviar solicitud
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={index.url()}>Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </PageContainer>
        </>
    );
}
