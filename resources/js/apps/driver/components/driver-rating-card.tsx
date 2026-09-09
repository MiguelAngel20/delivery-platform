import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { show } from '@/routes/driver/orders';

export type DriverReceivedRating = {
    id: number;
    order_id: number;
    order_number?: string | null;
    overall_rating: number;
    comment?: string | null;
    created_at?: string | null;
    customer_name: string;
};

type DriverRatingCardProps = {
    rating: DriverReceivedRating;
    className?: string;
};

function formatCreatedAt(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('es-MX', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

function StarDisplay({ value }: { value: number }) {
    return (
        <div
            className="flex gap-0.5"
            aria-label={`${value} de 5 estrellas`}
        >
            {[1, 2, 3, 4, 5].map((star) => (
                <span
                    key={star}
                    className={cn(
                        'text-lg leading-none',
                        star <= value
                            ? 'text-primary'
                            : 'text-muted-foreground/30',
                    )}
                >
                    ★
                </span>
            ))}
        </div>
    );
}

export function DriverRatingCard({ rating, className }: DriverRatingCardProps) {
    return (
        <article
            className={cn(
                'rounded-xl border border-border bg-surface p-4 shadow-sm',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 space-y-1">
                    <p className="font-semibold text-navy">
                        {rating.customer_name}
                    </p>
                    {rating.order_number ? (
                        <Link
                            href={show.url(rating.order_id)}
                            className="text-sm text-primary hover:underline"
                        >
                            Pedido #{rating.order_number}
                        </Link>
                    ) : null}
                    <p className="text-xs text-muted-foreground">
                        {formatCreatedAt(rating.created_at)}
                    </p>
                </div>
                <StarDisplay value={rating.overall_rating} />
            </div>

            {rating.comment ? (
                <p className="mt-3 text-sm text-muted-foreground">
                    “{rating.comment}”
                </p>
            ) : (
                <p className="mt-3 text-sm italic text-muted-foreground">
                    Sin comentario
                </p>
            )}
        </article>
    );
}
