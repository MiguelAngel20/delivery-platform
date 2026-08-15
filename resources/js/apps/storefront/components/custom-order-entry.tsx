import { Link } from '@inertiajs/react';
import { create } from '@/routes/customer/custom-orders';

export function CustomOrderEntry({ className = '' }: { className?: string }) {
    return (
        <p className={`text-center text-sm text-muted-foreground ${className}`}>
            ¿No encuentras lo que buscas?{' '}
            <Link
                href={create()}
                className="text-navy underline-offset-4 hover:underline"
            >
                Solicitar pedido personalizado
            </Link>
        </p>
    );
}
