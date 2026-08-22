import { Link, usePage } from '@inertiajs/react';
import { BrandLogo } from '@/components/brand-logo';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import {
    affiliation,
    feedback,
    privacy,
    terms,
} from '@/routes/legal';

type StorefrontFooterProps = {
    className?: string;
};

const legalLinks = [
    { title: 'Términos y condiciones', href: terms() },
    { title: 'Aviso de privacidad', href: privacy() },
    { title: 'Quejas y sugerencias', href: feedback() },
] as const;

const businessLinks = [
    { title: 'Contacto para afiliación', href: affiliation() },
] as const;

export function StorefrontFooter({ className }: StorefrontFooterProps) {
    const { name } = usePage().props as { name: string };
    const year = new Date().getFullYear();

    return (
        <footer
            className={cn(
                'mt-auto bg-navy text-navy-foreground',
                className,
            )}
        >
            <div className="mx-auto grid w-full max-w-6xl gap-8 px-4 py-10 md:grid-cols-3 md:px-6 md:py-12">
                <div className="space-y-3">
                    <Link href={home()} className="inline-flex">
                        <BrandLogo variant="onDark" />
                    </Link>
                    <p className="max-w-xs text-sm text-navy-foreground/80">
                        Pedidos y entregas en{' '}
                        <span className="font-medium text-primary">
                            Comitán de Domínguez, Chiapas
                        </span>
                        .
                    </p>
                </div>

                <div className="space-y-3">
                    <h2 className="text-sm font-semibold tracking-wide text-primary uppercase">
                        Legal y soporte
                    </h2>
                    <ul className="space-y-2 text-sm">
                        {legalLinks.map((link) => (
                            <li key={link.title}>
                                <Link
                                    href={link.href}
                                    className="text-navy-foreground/85 underline-offset-4 transition-colors hover:text-primary hover:underline"
                                >
                                    {link.title}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="space-y-3">
                    <h2 className="text-sm font-semibold tracking-wide text-primary uppercase">
                        Negocios
                    </h2>
                    <ul className="space-y-2 text-sm">
                        {businessLinks.map((link) => (
                            <li key={link.title}>
                                <Link
                                    href={link.href}
                                    className="text-navy-foreground/85 underline-offset-4 transition-colors hover:text-primary hover:underline"
                                >
                                    {link.title}
                                </Link>
                            </li>
                        ))}
                    </ul>
                    <p className="text-sm text-navy-foreground/80">
                        ¿Quieres afiliar tu negocio a{' '}
                        <span className="font-medium text-primary">RIDE</span>?
                        Escríbenos y te orientamos.
                    </p>
                </div>
            </div>

            <div className="border-t border-navy-foreground/15">
                <div className="mx-auto flex w-full max-w-6xl flex-col gap-1 px-4 py-4 text-xs text-navy-foreground/70 md:flex-row md:items-center md:justify-between md:px-6">
                    <p>
                        © {year}{' '}
                        <span className="font-medium text-primary">{name}</span>.
                        Todos los derechos reservados.
                    </p>
                    <p>Hecho para Comitán de Domínguez, Chiapas.</p>
                </div>
            </div>
        </footer>
    );
}
