import { Link, usePage } from '@inertiajs/react';
import { MessageCircle, ShoppingBag } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { create as register } from '@/actions/App/Http/Controllers/Web/Auth/CustomerRegisterController';
import { create as createCustomOrder } from '@/routes/customer/custom-orders';
import type { Auth } from '@/types';

type SupportProps = {
    whatsapp_url?: string | null;
    whatsapp_label?: string | null;
};

export function CustomOrderEntry({ className = '' }: { className?: string }) {
    const { auth, support } = usePage().props as {
        auth: Auth;
        support?: SupportProps;
    };

    const isCustomer = auth.user?.role === 'customer';
    const requestHref = isCustomer
        ? createCustomOrder()
        : register({ query: { continue: 'custom-order' } });

    const whatsappUrl =
        support?.whatsapp_url ?? 'https://wa.me/529633133731';
    const whatsappLabel =
        support?.whatsapp_label ?? 'Escribir por WhatsApp';

    return (
        <div
            className={cn(
                'flex flex-col items-center gap-3 rounded-2xl border border-primary/25 bg-gradient-to-b from-primary/10 to-primary/5 px-4 py-6 text-center shadow-sm',
                className,
            )}
        >
            <p className="text-base font-semibold text-navy">
                ¿No encuentras lo que buscas?
            </p>
            <p className="max-w-md text-sm text-muted-foreground">
                Solicita un pedido personalizado y te ayudamos a conseguirlo.
            </p>

            <div className="relative mt-1">
                <span
                    className="pointer-events-none absolute inset-0 animate-ping rounded-md bg-primary/35"
                    aria-hidden
                />
                <span
                    className="pointer-events-none absolute -inset-1 animate-pulse rounded-lg bg-primary/20"
                    aria-hidden
                />
                <Button
                    asChild
                    size="lg"
                    className="relative min-h-11 bg-primary px-6 text-primary-foreground shadow-md hover:bg-primary-hover"
                >
                    <Link href={requestHref}>
                        <ShoppingBag className="size-4" />
                        Solicitar pedido personalizado
                    </Link>
                </Button>
            </div>

            <a
                href={whatsappUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-2 text-sm font-medium text-navy underline-offset-4 hover:underline"
                data-test="custom-order-whatsapp-link"
            >
                <MessageCircle className="size-4 text-primary" />
                {whatsappLabel}
            </a>
        </div>
    );
}
