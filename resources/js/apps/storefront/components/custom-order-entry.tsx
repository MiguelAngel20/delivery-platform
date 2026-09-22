import { usePage } from '@inertiajs/react';
import { MessageCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type SupportProps = {
    whatsapp_url?: string | null;
    whatsapp_label?: string | null;
};

export function CustomOrderEntry({ className = '' }: { className?: string }) {
    const { support } = usePage().props as {
        support?: SupportProps;
    };

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
            data-test="custom-order-entry"
        >
            <p className="text-base font-semibold text-navy">
                ¿No encuentras lo que buscas?
            </p>
            <p className="max-w-md text-sm text-muted-foreground">
                Si no encuentras lo que necesitas en la app, puedes pedir tu
                pedido por WhatsApp y te ayudamos a conseguirlo.
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
                    <a
                        href={whatsappUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        data-test="custom-order-whatsapp-link"
                    >
                        <MessageCircle className="size-4" />
                        {whatsappLabel}
                    </a>
                </Button>
            </div>
        </div>
    );
}
