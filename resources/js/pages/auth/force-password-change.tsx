import { Form, Head, setLayoutProps } from '@inertiajs/react';
import ForcePasswordChangeController from '@/actions/App/Http/Controllers/Web/Auth/ForcePasswordChangeController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    passwordRules: string;
};

export default function ForcePasswordChange({ passwordRules }: Props) {
    setLayoutProps({
        title: 'Cambia tu contraseña',
        description:
            'Por seguridad debes reemplazar la contraseña temporal antes de continuar.',
    });

    return (
        <>
            <Head title="Cambiar contraseña" />

            <Form
                action={ForcePasswordChangeController.update.url()}
                method="put"
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">Nueva contraseña</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoFocus
                                autoComplete="new-password"
                                placeholder="Nueva contraseña"
                                passwordrules={passwordRules}
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirmar contraseña
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autoComplete="new-password"
                                placeholder="Confirmar contraseña"
                                passwordrules={passwordRules}
                            />
                            <InputError message={errors.password_confirmation} />
                        </div>

                        <Button
                            type="submit"
                            className="mt-4 w-full"
                            disabled={processing}
                            data-test="force-password-change-button"
                        >
                            {processing && <Spinner />}
                            Guardar contraseña
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}
