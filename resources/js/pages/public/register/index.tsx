import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/forms/form-field';
import { PageContainer } from '@/components/layout/page';
import { AddressPicker } from '@/components/maps/address-picker';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import {
    resolveFieldError,
    validateCustomerRegisterForm,
    type CustomerRegisterClientErrors,
    type CustomerRegisterDialCode,
} from '@/lib/auth/validate-customer-register-form';
import type { AddressValue } from '@/lib/maps/types';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    dialCodes: CustomerRegisterDialCode[];
    defaultDialCode: string;
};

export default function CustomerRegister({
    dialCodes,
    defaultDialCode,
}: Props) {
    const form = useForm({
        first_name: '',
        last_name: '',
        email: '',
        phone_dial_code: defaultDialCode,
        phone_national: '',
        password: '',
        password_confirmation: '',
        address_label: 'Casa',
        address_text: '',
        formatted_address: '',
        reference: '',
        latitude: '',
        longitude: '',
        place_id: '',
        google_maps_url: '',
    });
    const [clientErrors, setClientErrors] =
        useState<CustomerRegisterClientErrors>({});

    const fieldError = (key: string) =>
        resolveFieldError(key, clientErrors, form.errors);

    const clearFieldError = (key: string) => {
        setClientErrors((current) => {
            if (!(key in current)) {
                return current;
            }

            const next = { ...current };
            delete next[key];

            return next;
        });
    };

    const onAddressChange = (value: AddressValue) => {
        form.setData((data) => ({
            ...data,
            address_text: value.address_text,
            formatted_address: value.formatted_address ?? '',
            reference: value.reference ?? data.reference,
            latitude: String(value.latitude),
            longitude: String(value.longitude),
            place_id: value.place_id ?? '',
            google_maps_url: value.google_maps_url ?? '',
        }));
        clearFieldError('address_text');
    };

    const validateBeforeSubmit = (): boolean => {
        const validationErrors = validateCustomerRegisterForm(
            {
                first_name: form.data.first_name,
                last_name: form.data.last_name,
                email: form.data.email,
                phone_dial_code: form.data.phone_dial_code,
                phone_national: form.data.phone_national,
                password: form.data.password,
                password_confirmation: form.data.password_confirmation,
                address_label: form.data.address_label,
                address_text: form.data.address_text,
                latitude: form.data.latitude,
                longitude: form.data.longitude,
            },
            dialCodes,
        );

        if (Object.keys(validationErrors).length > 0) {
            setClientErrors(validationErrors);

            return false;
        }

        setClientErrors({});

        return true;
    };

    return (
        <>
            <Head title="Crear cuenta" />
            <PageContainer className="max-w-2xl gap-5 px-4 py-6 md:px-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-navy">
                        Crea tu cuenta para continuar
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Te enviaremos un código a tu correo para verificar la
                        cuenta. Tu dirección quedará guardada como
                        predeterminada.
                    </p>
                </div>

                <form
                    className="space-y-6"
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (!validateBeforeSubmit()) {
                            return;
                        }

                        form.post(store.url());
                    }}
                >
                    {Object.keys(clientErrors).length > 0 ? (
                        <div
                            role="alert"
                            className="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                        >
                            Revisa los campos marcados antes de continuar.
                        </div>
                    ) : null}

                    <section className="space-y-4 rounded-2xl border border-border bg-surface p-4 shadow-sm md:p-5">
                        <h2 className="font-semibold text-navy">Tus datos</h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Nombre(s)"
                                htmlFor="first_name"
                                required
                                error={fieldError('first_name')}
                            >
                                <Input
                                    id="first_name"
                                    value={form.data.first_name}
                                    onChange={(event) => {
                                        form.setData(
                                            'first_name',
                                            event.target.value,
                                        );
                                        clearFieldError('first_name');
                                    }}
                                    autoComplete="given-name"
                                    placeholder="Ej. María"
                                />
                            </FormField>
                            <FormField
                                label="Apellidos"
                                htmlFor="last_name"
                                required
                                error={fieldError('last_name')}
                            >
                                <Input
                                    id="last_name"
                                    value={form.data.last_name}
                                    onChange={(event) => {
                                        form.setData(
                                            'last_name',
                                            event.target.value,
                                        );
                                        clearFieldError('last_name');
                                    }}
                                    autoComplete="family-name"
                                    placeholder="Ej. García López"
                                />
                            </FormField>
                        </div>

                        <FormField
                            label="Correo electrónico"
                            htmlFor="email"
                            required
                            hint="Ahí te llegará el código para verificar tu cuenta."
                            error={fieldError('email')}
                        >
                            <Input
                                id="email"
                                type="email"
                                value={form.data.email}
                                onChange={(event) => {
                                    form.setData('email', event.target.value);
                                    clearFieldError('email');
                                }}
                                autoComplete="email"
                                placeholder="tucorreo@ejemplo.com"
                            />
                        </FormField>

                        <FormField
                            label="Teléfono"
                            htmlFor="phone_national"
                            required
                            error={
                                fieldError('phone_national') ??
                                fieldError('phone') ??
                                fieldError('phone_dial_code')
                            }
                        >
                            <div className="flex gap-2">
                                <select
                                    className="flex h-9 max-w-[11rem] rounded-md border border-input bg-background px-2 text-sm"
                                    value={form.data.phone_dial_code}
                                    onChange={(event) => {
                                        form.setData(
                                            'phone_dial_code',
                                            event.target.value,
                                        );
                                        clearFieldError('phone_dial_code');
                                        clearFieldError('phone_national');
                                    }}
                                    aria-label="Código de país"
                                >
                                    {dialCodes.map((item) => (
                                        <option
                                            key={item.dial}
                                            value={item.dial}
                                        >
                                            {item.label}
                                        </option>
                                    ))}
                                </select>
                                <Input
                                    id="phone_national"
                                    inputMode="numeric"
                                    value={form.data.phone_national}
                                    onChange={(event) => {
                                        form.setData(
                                            'phone_national',
                                            event.target.value.replace(
                                                /\D+/g,
                                                '',
                                            ),
                                        );
                                        clearFieldError('phone_national');
                                        clearFieldError('phone');
                                    }}
                                    autoComplete="tel-national"
                                    placeholder="Ej. 9611234567"
                                />
                            </div>
                        </FormField>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Contraseña"
                                htmlFor="password"
                                required
                                error={fieldError('password')}
                            >
                                <PasswordInput
                                    id="password"
                                    value={form.data.password}
                                    onChange={(event) => {
                                        form.setData(
                                            'password',
                                            event.target.value,
                                        );
                                        clearFieldError('password');
                                        clearFieldError(
                                            'password_confirmation',
                                        );
                                    }}
                                    autoComplete="new-password"
                                    placeholder="Crea una contraseña"
                                />
                            </FormField>
                            <FormField
                                label="Confirmar contraseña"
                                htmlFor="password_confirmation"
                                required
                                error={fieldError('password_confirmation')}
                            >
                                <PasswordInput
                                    id="password_confirmation"
                                    value={form.data.password_confirmation}
                                    onChange={(event) => {
                                        form.setData(
                                            'password_confirmation',
                                            event.target.value,
                                        );
                                        clearFieldError(
                                            'password_confirmation',
                                        );
                                        clearFieldError('password');
                                    }}
                                    autoComplete="new-password"
                                    placeholder="Repite tu contraseña"
                                />
                            </FormField>
                        </div>
                    </section>

                    <section className="space-y-4 rounded-2xl border border-border bg-surface p-4 shadow-sm md:p-5">
                        <div className="space-y-1">
                            <h2 className="font-semibold text-navy">
                                Dirección de entrega
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Será tu dirección predeterminada. Después puedes
                                agregar más desde tu perfil o al pedir.
                            </p>
                        </div>
                        <FormField
                            label="Etiqueta"
                            htmlFor="address_label"
                            error={fieldError('address_label')}
                        >
                            <Input
                                id="address_label"
                                value={form.data.address_label}
                                onChange={(event) => {
                                    form.setData(
                                        'address_label',
                                        event.target.value,
                                    );
                                    clearFieldError('address_label');
                                }}
                                placeholder="Ej. Casa, trabajo, oficina"
                            />
                        </FormField>
                        <FormField
                            error={
                                fieldError('address_text') ??
                                fieldError('latitude') ??
                                fieldError('longitude')
                            }
                        >
                            <AddressPicker
                                showReference
                                showCurrentLocation
                                onChange={onAddressChange}
                            />
                        </FormField>
                    </section>

                    <Button
                        type="submit"
                        className="h-11 w-full"
                        disabled={form.processing}
                    >
                        {form.processing ? <Spinner /> : null}
                        Terminar registro
                    </Button>

                    <p className="text-center text-sm text-muted-foreground">
                        ¿Ya tienes una cuenta?{' '}
                        <TextLink href={login()}>Inicia sesión</TextLink>
                    </p>
                </form>
            </PageContainer>
        </>
    );
}
