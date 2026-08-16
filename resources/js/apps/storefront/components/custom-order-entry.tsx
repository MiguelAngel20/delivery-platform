import { Link } from '@inertiajs/react';
import { ShoppingBag } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { create } from '@/routes/customer/custom-orders';

export function CustomOrderEntry({ className = '' }: { className?: string }) {
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
                    <Link href={create()}>
                        <ShoppingBag className="size-4" />
                        Solicitar pedido personalizado
                    </Link>
                </Button>
            </div>
        </div>
    );
}
