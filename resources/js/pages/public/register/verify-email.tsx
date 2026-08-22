import { Form, Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/forms/form-field';
import { PageContainer } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { validateCustomerEmailCode } from '@/lib/auth/validate-customer-register-form';
import { resend, store } from '@/routes/register/verify-email';

type Props = {
    email: string;
    maskedEmail: string;
};

export default function VerifyCustomerEmail({ maskedEmail }: Props) {
    const resendForm = useForm({});
    const [code, setCode] = useState('');
    const [clientError, setClientError] = useState<string | undefined>();

    return (
        <>
            <Head title="Verificar correo" />
            <PageContainer className="max-w-md gap-5 px-4 py-10 md:px-6">
                <div className="space-y-2 text-center">
                    <h1 className="text-2xl font-semibold text-navy">
                        Revisa tu correo
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Enviamos un código de 6 dígitos a{' '}
                        <span className="font-medium text-navy">
                            {maskedEmail}
                        </span>
                        . Escríbelo para activar tu cuenta.
                    </p>
                </div>

                <Form
                    {...store.form()}
                    noValidate
                    className="space-y-4 rounded-2xl border border-border bg-surface p-5 shadow-sm"
                    onBefore={() => {
                        const error = validateCustomerEmailCode(code);

                        if (error) {
                            setClientError(error);

                            return false;
                        }

                        setClientError(undefined);

                        return true;
                    }}
                >
                    {({ processing, errors }) => (
                        <>
                            <FormField
                                label="Código de verificación"
                                htmlFor="code"
                                required
                                error={clientError ?? errors.code}
                            >
                                <Input
                                    id="code"
                                    name="code"
                                    value={code}
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    maxLength={6}
                                    placeholder="Ej. 123456"
                                    className="text-center text-lg tracking-[0.4em]"
                                    autoFocus
                                    onChange={(event) => {
                                        setCode(
                                            event.target.value.replace(
                                                /\D+/g,
                                                '',
                                            ),
                                        );
                                        setClientError(undefined);
                                    }}
                                />
                            </FormField>
                            <Button
                                type="submit"
                                className="h-11 w-full"
                                disabled={processing}
                            >
                                {processing ? <Spinner /> : null}
                                Verificar y continuar
                            </Button>
                        </>
                    )}
                </Form>

                <p className="text-center text-sm text-muted-foreground">
                    ¿No llegó el correo?{' '}
                    <button
                        type="button"
                        className="font-medium text-primary underline-offset-4 hover:underline"
                        disabled={resendForm.processing}
                        onClick={() => resendForm.post(resend.url())}
                    >
                        {resendForm.processing
                            ? 'Enviando…'
                            : 'Reenviar código'}
                    </button>
                </p>
            </PageContainer>
        </>
    );
}
