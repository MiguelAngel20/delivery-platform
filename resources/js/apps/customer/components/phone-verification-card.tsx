import { router, useForm, usePage } from '@inertiajs/react';
import {
    getAuth,
    inMemoryPersistence,
    RecaptchaVerifier,
    setPersistence,
    signInWithPhoneNumber,
    signOut,
} from 'firebase/auth';
import type { ConfirmationResult } from 'firebase/auth';
import { useEffect, useRef, useState } from 'react';
import { store, update } from '@/actions/App/Http/Controllers/Web/Customer/PhoneVerificationController';
import { FormField } from '@/components/forms/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import {
    canSendPhoneCode,
    firebasePhoneErrorMessage,
    PHONE_RESEND_COOLDOWN_SECONDS,
    phoneResendLabel,
    tickCooldown,
} from '@/lib/auth/firebase-phone';
import { getFirebaseApp } from '@/lib/push/firebase';
import type { PushWebConfig } from '@/lib/push/firebase';

type DialOption = {
    dial: string;
    label: string;
    national_length: number;
};

type PhoneVerificationCardProps = {
    phoneDialCode: string;
    phoneNational: string;
    phoneVerified: boolean;
    dialOptions: DialOption[];
    firebaseReady: boolean;
};

type Phase = 'idle' | 'sending' | 'code' | 'verifying' | 'verified' | 'error';

export function PhoneVerificationCard({
    phoneDialCode,
    phoneNational,
    phoneVerified,
    dialOptions,
    firebaseReady,
}: PhoneVerificationCardProps) {
    const { push } = usePage().props as {
        push?: { web?: PushWebConfig };
    };
    const phoneForm = useForm({
        phone_dial_code: '+52',
        phone_national: phoneNational,
    });
    const [phase, setPhase] = useState<Phase>(phoneVerified ? 'verified' : 'idle');
    const [codeStep, setCodeStep] = useState(false);
    const [code, setCode] = useState('');
    const [message, setMessage] = useState<string | undefined>();
    const [cooldown, setCooldown] = useState(0);
    const lockRef = useRef(false);
    const verifierRef = useRef<RecaptchaVerifier | null>(null);
    const confirmationRef = useRef<ConfirmationResult | null>(null);

    const phoneErrors = phoneForm.errors as Partial<
        Record<'phone' | 'phone_dial_code' | 'phone_national', string>
    >;
    const phoneChanged =
        phoneDialCode !== '+52' ||
        phoneForm.data.phone_national !== phoneNational;
    const busy = phase === 'sending' || phase === 'verifying' || phoneForm.processing;
    const expectedLength =
        dialOptions.find((option) => option.dial === '+52')?.national_length ??
        10;

    useEffect(() => {
        if (cooldown <= 0) {
            return;
        }

        const timer = window.setTimeout(() => {
            setCooldown((current) => tickCooldown(current));
        }, 1000);

        return () => window.clearTimeout(timer);
    }, [cooldown]);

    useEffect(() => {
        return () => {
            clearVerifier();
        };
    }, []);

    function clearVerifier(): void {
        try {
            verifierRef.current?.clear();
        } catch {
            // The widget may already be gone.
        }

        verifierRef.current = null;
    }

    async function firebaseAuth() {
        const web = push?.web;

        if (!web?.authDomain) {
            return null;
        }

        const firebaseApp = getFirebaseApp(web);

        if (!firebaseApp) {
            return null;
        }

        const auth = getAuth(firebaseApp);
        await setPersistence(auth, inMemoryPersistence);

        return auth;
    }

    async function sendCode(): Promise<void> {
        if (
            lockRef.current ||
            !canSendPhoneCode({ busy, cooldownSeconds: cooldown }) ||
            phoneChanged
        ) {
            return;
        }

        const e164 = `+52${phoneForm.data.phone_national}`;

        if (phoneForm.data.phone_national.length !== expectedLength) {
            setPhase('error');
            setMessage(
                `El número debe tener ${expectedLength} dígitos para ese país.`,
            );

            return;
        }

        lockRef.current = true;
        setPhase('sending');
        setMessage(undefined);
        clearVerifier();

        try {
            const auth = await firebaseAuth();
            const container = document.getElementById('customer-phone-recaptcha');

            if (!auth || !container) {
                setPhase('error');
                setMessage(
                    'La verificación por SMS todavía no está disponible.',
                );

                return;
            }

            const verifier = new RecaptchaVerifier(auth, container, {
                size: 'invisible',
                callback: () => undefined,
                'expired-callback': () => {
                    clearVerifier();
                    setPhase('error');
                    setMessage(
                        'La verificación de seguridad expiró. Envía el código otra vez.',
                    );
                },
            });
            verifierRef.current = verifier;

            const confirmation = await signInWithPhoneNumber(
                auth,
                e164,
                verifier,
            );
            confirmationRef.current = confirmation;
            setCodeStep(true);
            setPhase('code');
            setCooldown(PHONE_RESEND_COOLDOWN_SECONDS);
            setMessage('SMS enviado. Escribe el código de 6 dígitos.');
        } catch (error) {
            clearVerifier();
            confirmationRef.current = null;
            setCodeStep(false);
            setPhase('error');
            setMessage(firebasePhoneErrorMessage(error));
        } finally {
            lockRef.current = false;
        }
    }

    async function verifyCode(): Promise<void> {
        if (lockRef.current || phase === 'verifying') {
            return;
        }

        if (code.length !== 6) {
            setMessage('Escribe el código de 6 dígitos.');

            return;
        }

        const confirmation = confirmationRef.current;

        if (!confirmation) {
            setPhase('error');
            setMessage('El código ha expirado. Solicita uno nuevo.');

            return;
        }

        lockRef.current = true;
        setPhase('verifying');
        setMessage(undefined);

        try {
            const credential = await confirmation.confirm(code);
            const idToken = await credential.user.getIdToken();
            const auth = await firebaseAuth();

            router.post(
                store.url(),
                { firebase_id_token: idToken },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setPhase('verified');
                        setMessage('Teléfono verificado');

                        if (auth) {
                            void signOut(auth);
                        }
                    },
                    onError: (errors) => {
                        setPhase('error');
                        setMessage(
                            errors.firebase_id_token ??
                                'No se pudo confirmar la verificación del teléfono.',
                        );

                        if (auth) {
                            void signOut(auth);
                        }
                    },
                    onFinish: () => {
                        lockRef.current = false;
                    },
                },
            );
        } catch (error) {
            setPhase('error');
            setMessage(firebasePhoneErrorMessage(error));
            lockRef.current = false;
            const auth = await firebaseAuth();

            if (auth) {
                void signOut(auth);
            }
        }
    }

    return (
        <div className="mt-6 space-y-4 border-t border-border pt-4">
            <div className="space-y-1">
                <p className="text-sm font-medium text-navy">
                    Verificación del teléfono
                </p>
                <p className="text-sm text-muted-foreground">
                    Te enviamos un SMS para confirmar que el número es tuyo.
                    La sesión de ChisDrive sigue siendo la de tu cuenta.
                </p>
            </div>

            {phase === 'verified' && !phoneChanged ? (
                <p className="text-sm font-medium text-primary">
                    Teléfono verificado
                </p>
            ) : null}

            <FormField
                label="Teléfono"
                htmlFor="phone_national"
                error={
                    phoneErrors.phone_national ??
                    phoneErrors.phone ??
                    phoneErrors.phone_dial_code
                }
            >
                <div className="flex gap-2">
                    <select
                        id="phone_dial_code"
                        className="border-input flex h-9 w-[4.25rem] shrink-0 rounded-md border bg-muted px-1 text-sm font-medium tabular-nums shadow-xs outline-none disabled:cursor-not-allowed disabled:opacity-100"
                        value="+52"
                        aria-label="Código de país"
                        aria-disabled="true"
                        disabled
                    >
                        <option value="+52">+52</option>
                    </select>
                    <Input
                        id="phone_national"
                        inputMode="numeric"
                        autoComplete="tel-national"
                        value={phoneForm.data.phone_national}
                        disabled={busy}
                        onChange={(event) =>
                            phoneForm.setData(
                                'phone_national',
                                event.target.value.replace(/\D+/g, ''),
                            )
                        }
                    />
                </div>
            </FormField>

            {phoneChanged ? (
                <Button
                    type="button"
                    className="h-11 w-full"
                    disabled={phoneForm.processing}
                    onClick={() =>
                        phoneForm.patch(update.url(), { preserveScroll: true })
                    }
                >
                    {phoneForm.processing ? <Spinner /> : null}
                    Guardar número
                </Button>
            ) : null}

            {!firebaseReady ? (
                <p className="text-sm text-muted-foreground">
                    La verificación por SMS todavía no está disponible.
                </p>
            ) : null}

            {firebaseReady && !phoneChanged && phase !== 'verified' ? (
                <Button
                    type="button"
                    className="h-11 w-full"
                    disabled={
                        !canSendPhoneCode({
                            busy,
                            cooldownSeconds: cooldown,
                        })
                    }
                    onClick={() => {
                        void sendCode();
                    }}
                >
                    {phase === 'sending' ? <Spinner /> : null}
                    {phase === 'sending'
                        ? 'Enviando SMS…'
                        : cooldown > 0
                          ? phoneResendLabel(cooldown)
                          : phase === 'code' || phase === 'error'
                            ? phoneResendLabel(cooldown)
                            : 'Enviar código'}
                </Button>
            ) : null}

            {codeStep && phase !== 'verified' && !phoneChanged ? (
                <div className="space-y-3">
                    <FormField label="Código SMS" htmlFor="sms_code" required>
                        <Input
                            id="sms_code"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            maxLength={6}
                            value={code}
                            placeholder="123456"
                            className="text-center text-lg tracking-[0.4em]"
                            disabled={phase === 'verifying'}
                            onChange={(event) =>
                                setCode(event.target.value.replace(/\D+/g, ''))
                            }
                        />
                    </FormField>
                    <Button
                        type="button"
                        className="h-11 w-full"
                        disabled={phase === 'verifying' || code.length !== 6}
                        onClick={() => {
                            void verifyCode();
                        }}
                    >
                        {phase === 'verifying' ? <Spinner /> : null}
                        {phase === 'verifying' ? 'Verificando…' : 'Verificar'}
                    </Button>
                </div>
            ) : null}

            {message ? (
                <p
                    className={
                        phase === 'error'
                            ? 'text-sm text-destructive'
                            : 'text-sm text-muted-foreground'
                    }
                >
                    {message}
                </p>
            ) : null}

            <div id="customer-phone-recaptcha" />
        </div>
    );
}
