import { Head, router, usePage } from '@inertiajs/react';
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
import { RecaptchaNotice } from '@/components/auth/recaptcha-notice';
import { FormField } from '@/components/forms/form-field';
import { PageContainer } from '@/components/layout/page';
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
import { store } from '@/routes/register/verify-phone';

type Props = {
    phone: string;
    maskedPhone: string;
    firebaseReady: boolean;
};

export default function VerifyCustomerPhone({
    phone,
    maskedPhone,
    firebaseReady,
}: Props) {
    const { push } = usePage().props as {
        push?: { web?: PushWebConfig };
    };
    const [code, setCode] = useState('');
    const [message, setMessage] = useState<string | undefined>();
    const [phase, setPhase] = useState<'idle' | 'sending' | 'code' | 'verifying' | 'error'>('idle');
    const [cooldown, setCooldown] = useState(0);
    const verifierRef = useRef<RecaptchaVerifier | null>(null);
    const confirmationRef = useRef<ConfirmationResult | null>(null);
    const lockRef = useRef(false);

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
            try {
                verifierRef.current?.clear();
            } catch {
                // The widget may already be gone.
            }
        };
    }, []);

    async function sendCode(): Promise<void> {
        if (
            lockRef.current ||
            !firebaseReady ||
            !canSendPhoneCode({ busy: phase === 'sending', cooldownSeconds: cooldown })
        ) {
            return;
        }

        const web = push?.web;
        const container = document.getElementById('register-phone-recaptcha');

        if (!web?.authDomain || !container) {
            setPhase('error');
            setMessage('La verificación por SMS todavía no está disponible.');

            return;
        }

        const firebaseApp = getFirebaseApp(web);

        if (!firebaseApp) {
            setPhase('error');
            setMessage('La verificación por SMS todavía no está disponible.');

            return;
        }

        lockRef.current = true;
        setPhase('sending');
        setMessage(undefined);

        try {
            verifierRef.current?.clear();
        } catch {
            // Ignore a widget that was already removed.
        }

        try {
            const auth = getAuth(firebaseApp);
            await setPersistence(auth, inMemoryPersistence);
            const verifier = new RecaptchaVerifier(auth, container, {
                size: 'invisible',
            });
            verifierRef.current = verifier;
            confirmationRef.current = await signInWithPhoneNumber(auth, phone, verifier);
            setPhase('code');
            setCooldown(PHONE_RESEND_COOLDOWN_SECONDS);
            setMessage('SMS enviado. Escribe el código de 6 dígitos.');
        } catch (error) {
            confirmationRef.current = null;
            setPhase('error');
            setMessage(firebasePhoneErrorMessage(error));
        } finally {
            lockRef.current = false;
        }
    }

    async function verifyCode(): Promise<void> {
        if (lockRef.current || code.length !== 6) {
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
            const web = push?.web;
            const firebaseApp = web ? getFirebaseApp(web) : null;
            const auth = firebaseApp ? getAuth(firebaseApp) : null;

            router.post(
                store.url(),
                { firebase_id_token: idToken },
                {
                    onError: (errors) => {
                        setPhase('error');
                        setMessage(
                            errors.firebase_id_token ??
                                'No se pudo confirmar la verificación del teléfono.',
                        );
                        lockRef.current = false;

                        if (auth) {
                            void signOut(auth);
                        }
                    },
                    onFinish: () => {
                        if (auth) {
                            void signOut(auth);
                        }
                    },
                },
            );
        } catch (error) {
            setPhase('error');
            setMessage(firebasePhoneErrorMessage(error));
            lockRef.current = false;
        }
    }

    const busy = phase === 'sending' || phase === 'verifying';

    return (
        <>
            <Head title="Verificar teléfono" />
            <PageContainer className="max-w-md gap-5 px-4 py-10 md:px-6">
                <div className="space-y-2 text-center">
                    <h1 className="text-2xl font-semibold text-navy">
                        Confirma tu teléfono
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Te enviamos un código por SMS a{' '}
                        <span className="font-medium text-navy">{maskedPhone}</span>
                        . Con ese código se activa tu cuenta.
                    </p>
                </div>

                <div className="space-y-4 rounded-2xl border border-border bg-surface p-5 shadow-sm">
                    <div id="register-phone-recaptcha" />
                    <Button
                        type="button"
                        className="h-11 w-full"
                        disabled={
                            !firebaseReady ||
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
                              : 'Enviar código'}
                    </Button>

                    {phase === 'code' || phase === 'verifying' || phase === 'error' ? (
                        <FormField
                            label="Código SMS"
                            htmlFor="sms_code"
                            required
                            error={message}
                        >
                            <Input
                                id="sms_code"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                maxLength={6}
                                value={code}
                                placeholder="123456"
                                className="text-center text-lg tracking-[0.4em]"
                                onChange={(event) => {
                                    setCode(event.target.value.replace(/\D+/g, ''));
                                    setMessage(undefined);
                                }}
                            />
                        </FormField>
                    ) : message ? (
                        <p className="text-sm text-muted-foreground">{message}</p>
                    ) : null}

                    {phase === 'code' || phase === 'verifying' ? (
                        <Button
                            type="button"
                            className="h-11 w-full"
                            disabled={busy || code.length !== 6}
                            onClick={() => {
                                void verifyCode();
                            }}
                        >
                            {phase === 'verifying' ? <Spinner /> : null}
                            Activar cuenta
                        </Button>
                    ) : null}

                    {!firebaseReady ? (
                        <p className="text-sm text-muted-foreground">
                            La verificación por SMS todavía no está disponible.
                        </p>
                    ) : null}

                    <RecaptchaNotice />
                </div>
            </PageContainer>
        </>
    );
}
