import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

type Props = {
    firstName: string;
    loginUrl: string;
};

export default function DriverEmailVerified({ firstName, loginUrl }: Props) {
    setLayoutProps({
        title: 'Correo verificado',
        description: 'Tu cuenta de repartidor ya está lista.',
    });

    return (
        <>
            <Head title="Correo verificado" />
            <div className="space-y-5 text-center">
                <div className="space-y-2">
                    <h1 className="text-xl font-semibold text-navy">
                        ¡Bienvenido/a{firstName ? `, ${firstName}` : ''}!
                    </h1>
                    <p className="text-sm leading-relaxed text-muted-foreground">
                        Tu correo fue verificado correctamente. Ya puedes iniciar
                        sesión en el portal de repartidores.
                    </p>
                </div>
                <Button asChild className="w-full">
                    <Link href={loginUrl}>Ir a iniciar sesión</Link>
                </Button>
            </div>
        </>
    );
}
