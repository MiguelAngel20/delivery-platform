import { cn } from '@/lib/utils';

type LoyaltyProgress = {
    qualifying_orders_count: number;
    required_orders: number;
    slots: boolean[];
    progress_ratio: number;
    reward_ready: boolean;
    pending_reward_amount: string | null;
    next_service_fee_discount: string;
    next_discount_label: string | null;
    launch_available: boolean;
    service_fee: string;
    service_fee_after_discount: string;
};

type LoyaltyProgressCardProps = {
    loyalty: LoyaltyProgress;
    className?: string;
    /** Compact strip for the account dropdown. */
    variant?: 'card' | 'menu';
};

function formatMoney(value: string | number): string {
    const amount = typeof value === 'number' ? value : Number(value);

    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(Number.isFinite(amount) ? amount : 0);
}

function launchPercent(loyalty: LoyaltyProgress): number {
    const fee = Number(loyalty.service_fee);
    const after = Number(loyalty.service_fee_after_discount);

    if (!Number.isFinite(fee) || fee <= 0 || !Number.isFinite(after)) {
        return 50;
    }

    return Math.max(0, Math.round(((fee - after) / fee) * 100));
}

function ServiceFeePrice({
    loyalty,
    size = 'md',
}: {
    loyalty: LoyaltyProgress;
    size?: 'sm' | 'md';
}) {
    return (
        <div className="flex items-baseline gap-2">
            <span
                className={cn(
                    'font-medium text-muted-foreground line-through decoration-2',
                    size === 'sm' ? 'text-xs' : 'text-sm',
                )}
            >
                {formatMoney(loyalty.service_fee)}
            </span>
            <span
                className={cn(
                    'font-bold text-primary',
                    size === 'sm' ? 'text-sm' : 'text-xl',
                )}
            >
                {formatMoney(loyalty.service_fee_after_discount)}
            </span>
            <span
                className={cn(
                    'text-muted-foreground',
                    size === 'sm' ? 'text-[10px]' : 'text-xs',
                )}
            >
                de servicio
            </span>
        </div>
    );
}

function ProgressRing({
    loyalty,
    size,
    stroke,
}: {
    loyalty: LoyaltyProgress;
    size: number;
    stroke: number;
}) {
    const radius = (size - stroke) / 2;
    const circumference = 2 * Math.PI * radius;
    const progress = Math.min(1, Math.max(0, loyalty.progress_ratio));
    const offset = circumference * (1 - progress);

    return (
        <div
            className="relative shrink-0"
            style={{ width: size, height: size }}
            aria-hidden
        >
            <svg width={size} height={size} className="-rotate-90">
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={stroke}
                    className="text-border"
                />
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={stroke}
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                    className="text-primary transition-[stroke-dashoffset] duration-500"
                />
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center text-center">
                <span
                    className={cn(
                        'font-semibold text-navy',
                        size >= 80 ? 'text-lg' : 'text-sm',
                    )}
                >
                    {loyalty.qualifying_orders_count}/{loyalty.required_orders}
                </span>
            </div>
        </div>
    );
}

function SlotDots({
    slots,
    size = 'md',
}: {
    slots: boolean[];
    size?: 'sm' | 'md';
}) {
    return (
        <div className="flex flex-wrap gap-1.5">
            {slots.map((filled, index) => (
                <span
                    key={`slot-${index}`}
                    className={cn(
                        'rounded-full border',
                        size === 'sm' ? 'size-2.5' : 'size-3',
                        filled
                            ? 'border-primary bg-primary'
                            : 'border-border bg-background',
                    )}
                    aria-label={
                        filled
                            ? `Pedido ${index + 1} completado`
                            : `Pedido ${index + 1} pendiente`
                    }
                />
            ))}
        </div>
    );
}

export function LoyaltyProgressCard({
    loyalty,
    className,
    variant = 'card',
}: LoyaltyProgressCardProps) {
    const percent = launchPercent(loyalty);

    if (variant === 'menu') {
        return (
            <div
                className={cn('space-y-2 px-2 py-1.5', className)}
                data-test="loyalty-progress-menu"
            >
                <div className="flex items-center gap-3">
                    <ProgressRing loyalty={loyalty} size={52} stroke={6} />
                    <div className="min-w-0 flex-1 space-y-1.5">
                        <p className="text-xs font-semibold text-navy">
                            Tu progreso
                        </p>
                        <SlotDots slots={loyalty.slots} size="sm" />
                    </div>
                </div>
                {loyalty.launch_available ? (
                    <div className="space-y-1">
                        <p className="text-[11px] leading-snug text-muted-foreground">
                            Realiza tu primer pedido y obtén {percent}% de
                            descuento en el servicio.
                        </p>
                        <ServiceFeePrice loyalty={loyalty} size="sm" />
                    </div>
                ) : loyalty.reward_ready ? (
                    <p className="text-[11px] leading-snug text-muted-foreground">
                        Tienes {formatMoney(loyalty.pending_reward_amount ?? 0)}{' '}
                        de descuento listos para tu próximo servicio.
                    </p>
                ) : (
                    <p className="text-[11px] leading-snug text-muted-foreground">
                        Completa {loyalty.required_orders} pedidos y desbloquea
                        un descuento en el servicio.
                    </p>
                )}
            </div>
        );
    }

    return (
        <section
            className={cn(
                'rounded-xl border border-border bg-surface p-4 shadow-sm',
                className,
            )}
            data-test="loyalty-progress-card"
        >
            <div className="flex items-start gap-4">
                <ProgressRing loyalty={loyalty} size={88} stroke={8} />

                <div className="min-w-0 flex-1 space-y-2">
                    <h2 className="font-semibold text-navy">Tu progreso</h2>
                    {loyalty.launch_available ? (
                        <div className="space-y-2">
                            <p className="text-sm leading-snug text-muted-foreground">
                                Realiza tu primer pedido y obtén{' '}
                                <span className="font-semibold text-navy">
                                    {percent}% de descuento
                                </span>{' '}
                                en el servicio.
                            </p>
                            <div className="rounded-lg bg-primary/5 px-3 py-2">
                                <ServiceFeePrice loyalty={loyalty} />
                            </div>
                        </div>
                    ) : loyalty.reward_ready ? (
                        <p className="text-sm text-muted-foreground">
                            Recompensa lista:{' '}
                            {formatMoney(loyalty.pending_reward_amount ?? 0)} de
                            descuento en el servicio de tu próximo pedido.
                        </p>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Completa {loyalty.required_orders} pedidos y
                            desbloquea un descuento en el servicio.
                        </p>
                    )}

                    <div className="pt-1">
                        <SlotDots slots={loyalty.slots} />
                    </div>
                </div>
            </div>
        </section>
    );
}

export type { LoyaltyProgress };
