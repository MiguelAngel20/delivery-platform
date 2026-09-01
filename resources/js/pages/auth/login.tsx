import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { storefrontGoBack } from '@/apps/storefront/hooks/use-storefront-shell';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { create as register } from '@/actions/App/Http/Controllers/Web/Auth/CustomerRegisterController';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
    title?: string;
    description?: string;
    submitLabel?: string;
    portal?: string;
};

export default function Login({
    status,
    canResetPassword,
    title = 'Iniciar sesión',
    description = 'Accede a tu cuenta',
    submitLabel = 'Entrar',
    portal,
}: Props) {
    setLayoutProps({
        title,
        description,
    });

    return (
        <>
            <Head title={title} />

            {portal === 'customer' ? (
                <div className="fixed inset-x-0 top-0 z-20 flex items-center px-2 py-2 md:hidden">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-9"
                        aria-label="Volver"
                        data-test="login-back-button"
                        onClick={storefrontGoBack}
                    >
                        <ArrowLeft className="size-5" />
                    </Button>
                </div>
            ) : null}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Correo</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="correo@ejemplo.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">Contraseña</Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-sm"
                                            tabIndex={5}
                                        >
                                            ¿Olvidaste tu contraseña?
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Contraseña"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">Recordarme</Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                {submitLabel}
                            </Button>
                        </div>
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            {portal === 'customer' ? (
                <p className="text-center text-sm text-muted-foreground">
                    ¿No tienes cuenta?{' '}
                    <TextLink href={register()} tabIndex={6}>
                        Regístrate
                    </TextLink>
                </p>
            ) : null}
        </>
    );
}
