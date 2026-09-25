import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { CoverageUnavailableBanner } from '@/apps/storefront/components/coverage-unavailable-banner';
import {
    COVERAGE_UNAVAILABLE_MESSAGE,
    checkDeliveryCoverage,
} from '@/apps/storefront/lib/check-delivery-coverage';
import { LoadingDialog } from '@/components/feedback/loading-dialog';
import { notify } from '@/components/feedback/toast';
import { FormField } from '@/components/forms/form-field';
import { PageContainer } from '@/components/layout/page';
import { AddressPicker } from '@/components/maps/address-picker';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    resolveFieldError,
    validateCustomerRegisterForm,
    type CustomerRegisterClientErrors,
    type CustomerRegisterDialCode,
} from '@/lib/auth/validate-customer-register-form';
import { PASSWORD_REQUIREMENTS_HINT } from '@/lib/auth/password-requirements';
import type { AddressValue } from '@/lib/maps/types';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    dialCodes: CustomerRegisterDialCode[];
    defaultDialCode: string;
    passwordRules: string;
};

const REGISTER_FIELD_ORDER = [
    'first_name',
    'last_name',
    'email',
    'phone_national',
    'phone_dial_code',
    'password',
    'password_confirmation',
    'address_label',
    'address_text',
    'reference',
    'latitude',
    'longitude',
] as const;

const FIELD_ELEMENT_IDS: Record<string, string> = {
    first_name: 'first_name',
    last_name: 'last_name',
    email: 'email',
    phone_national: 'phone_national',
    phone_dial_code: 'phone_dial_code',
    password: 'password',
    password_confirmation: 'password_confirmation',
    address_label: 'address_label',
    address_text: 'register-address-section',
    reference: 'address_reference',
    latitude: 'register-address-section',
    longitude: 'register-address-section',
};

function focusFirstRegisterError(
    errors: CustomerRegisterClientErrors | Record<string, string>,
): void {
    const firstKey = REGISTER_FIELD_ORDER.find((key) => Boolean(errors[key]));

    if (!firstKey) {
        return;
    }

    const elementId = FIELD_ELEMENT_IDS[firstKey] ?? firstKey;
    const element = document.getElementById(elementId);

    if (!element) {
        return;
    }

    element.scrollIntoView({ behavior: 'smooth', block: 'center' });

    if (
        element instanceof HTMLInputElement ||
        element instanceof HTMLTextAreaElement ||
        element instanceof HTMLSelectElement
    ) {
        window.setTimeout(() => {
            element.focus({ preventScroll: true });
        }, 280);
    }
}

export default function CustomerRegister({
    dialCodes,
    defaultDialCode,
    passwordRules,
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
    const [coverageError, setCoverageError] = useState<string | null>(null);
    const [checkingCoverage, setCheckingCoverage] = useState(false);

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
        clearFieldError('latitude');
        clearFieldError('longitude');
        clearFieldError('reference');
        setCoverageError(null);

        void (async () => {
            setCheckingCoverage(true);

            try {
                const result = await checkDeliveryCoverage(
                    value.latitude,
                    value.longitude,
                );

                if (!result.covered) {
                    setCoverageError(
                        result.message ?? COVERAGE_UNAVAILABLE_MESSAGE,
                    );
                }
            } catch {
                // Server will still validate on submit.
            } finally {
                setCheckingCoverage(false);
            }
        })();
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
                reference: form.data.reference,
                latitude: form.data.latitude,
                longitude: form.data.longitude,
            },
            dialCodes,
        );

        if (Object.keys(validationErrors).length > 0) {
            setClientErrors(validationErrors);
            notify.error(
                'Faltan datos por completar. Revisa los campos marcados.',
            );
            window.setTimeout(
                () => focusFirstRegisterError(validationErrors),
                50,
            );

            return false;
        }

        if (coverageError) {
            const coverageErrors = {
                latitude: coverageError,
            };
            setClientErrors(coverageErrors);
            notify.error(coverageError);
            window.setTimeout(
                () => focusFirstRegisterError(coverageErrors),
                50,
            );

            return false;
        }

        setClientErrors({});

        return true;
    };

    const blockingMessages = Array.from(
        new Set(
            REGISTER_FIELD_ORDER.map((key) => fieldError(key)).filter(
                (message): message is string => Boolean(message),
            ),
        ),
    );

    return (
        <>
            <Head title="Crear cuenta" />
            <PageContainer className="max-w-2xl gap-5 px-4 py-6 md:px-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-navy">
                        Crea tu cuenta para continuar
                    </h1>
                </div>

                <form
                    className="space-y-6"
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (!validateBeforeSubmit()) {
                            return;
                        }

                        form.post(store.url(), {
                            onError: (errors) => {
                                notify.error(
                                    'No se pudo completar el registro. Revisa los campos marcados.',
                                );
                                window.setTimeout(
                                    () => focusFirstRegisterError(errors),
                                    50,
                                );
                            },
                        });
                    }}
                >
                    {blockingMessages.length > 0 ? (
                        <div
                            role="alert"
                            className="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                        >
                            <p className="font-medium">
                                Revisa estos puntos antes de continuar:
                            </p>
                            <ul className="mt-2 list-disc space-y-1 pl-5">
                                {blockingMessages.map((message) => (
                                    <li key={message}>{message}</li>
                                ))}
                            </ul>
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
                            hint="Formato completo: tucorreo@ejemplo.com (el @ es obligatorio)."
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
                            hint="Solo dígitos del número local (sin espacios ni guiones)."
                            error={
                                fieldError('phone_national') ??
                                fieldError('phone') ??
                                fieldError('phone_dial_code')
                            }
                        >
                            <div className="flex gap-2">
                                <select
                                    id="phone_dial_code"
                                    className="border-input flex h-9 w-[4.25rem] shrink-0 rounded-md border bg-background px-1 text-sm font-medium tabular-nums shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
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
                                            {item.dial}
                                        </option>
                                    ))}
                                </select>
                                <Input
                                    id="phone_national"
                                    className="min-w-0 flex-1"
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
                                hint={PASSWORD_REQUIREMENTS_HINT}
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
                                    passwordrules={passwordRules}
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
                                    passwordrules={passwordRules}
                                />
                            </FormField>
                        </div>
                    </section>

                    <section
                        id="register-address-section"
                        className="space-y-4 rounded-2xl border border-border bg-surface p-4 shadow-sm md:p-5"
                    >
                        <div className="space-y-2">
                            <h2 className="font-semibold text-navy">
                                Dirección de entrega
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Esta ubicación se guarda para tus entregas y la
                                usaremos en tu primer pedido. Después de
                                completar un pedido podrás agregar hasta 3
                                direcciones más desde tu perfil.
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
                                fieldError('longitude') ??
                                fieldError('reference')
                            }
                        >
                            <AddressPicker
                                value={{
                                    address_text: form.data.address_text,
                                    formatted_address:
                                        form.data.formatted_address || null,
                                    reference: form.data.reference || null,
                                    latitude:
                                        form.data.latitude === ''
                                            ? undefined
                                            : Number(form.data.latitude),
                                    longitude:
                                        form.data.longitude === ''
                                            ? undefined
                                            : Number(form.data.longitude),
                                    place_id: form.data.place_id || null,
                                    google_maps_url:
                                        form.data.google_maps_url || null,
                                }}
                                showReference
                                referenceRequired
                                currentLocationOnly
                                onChange={onAddressChange}
                            />
                        </FormField>
                        {coverageError ||
                        fieldError('latitude')?.includes('cobertura') ? (
                            <CoverageUnavailableBanner
                                message={
                                    coverageError ?? fieldError('latitude')
                                }
                            />
                        ) : null}
                        {checkingCoverage ? (
                            <p className="text-xs text-muted-foreground">
                                Verificando cobertura…
                            </p>
                        ) : null}
                    </section>

                    {blockingMessages.length > 0 ? (
                        <div
                            role="alert"
                            className="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                        >
                            <p className="font-medium">
                                No puedes terminar el registro todavía:
                            </p>
                            <ul className="mt-2 list-disc space-y-1 pl-5">
                                {blockingMessages.map((message) => (
                                    <li key={message}>{message}</li>
                                ))}
                            </ul>
                        </div>
                    ) : null}

                    <Button
                        type="submit"
                        className="h-11 w-full"
                        disabled={form.processing || Boolean(coverageError)}
                    >
                        Terminar registro
                    </Button>

                    <p className="text-center text-sm text-muted-foreground">
                        ¿Ya tienes una cuenta?{' '}
                        <TextLink href={login()}>Inicia sesión</TextLink>
                    </p>
                </form>
            </PageContainer>

            <LoadingDialog
                open={form.processing}
                title="Creando tu cuenta…"
                description="Estamos terminando tu registro. No cierres esta ventana."
            />
        </>
    );
}
