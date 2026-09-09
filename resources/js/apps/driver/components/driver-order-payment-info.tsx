import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';

export type DriverOrderPayment = {
    payment_method: string;
    payment_method_label: string;
    business_payment: string;
    total: string;
};

type DriverOrderPaymentInfoProps = {
    payment: DriverOrderPayment;
    className?: string;
    compact?: boolean;
};

function PaymentRows({
    payment,
    compact,
}: {
    payment: DriverOrderPayment;
    compact: boolean;
}) {
    const isCash = payment.payment_method === 'cash';
    const rowClass = compact
        ? 'flex items-center justify-between gap-3'
        : 'flex items-center justify-between gap-3';
    const labelClass = 'text-muted-foreground';
    const valueClass = compact
        ? 'font-medium text-navy'
        : 'font-medium text-navy';
    const totalValueClass = compact
        ? 'font-semibold text-navy'
        : 'text-base font-semibold text-navy';

    return (
        <>
            <div className={rowClass}>
                <span className={labelClass}>Formato de pago</span>
                <span className={valueClass}>{payment.payment_method_label}</span>
            </div>
            {isCash ? (
                <>
                    <div className={rowClass}>
                        <span className={labelClass}>Pago en negocio</span>
                        <span className={valueClass}>
                            {formatMoney(payment.business_payment)}
                        </span>
                    </div>
                    <div className={rowClass}>
                        <span className={labelClass}>Total a cobrar</span>
                        <span className={totalValueClass}>
                            {formatMoney(payment.total)}
                        </span>
                    </div>
                </>
            ) : null}
        </>
    );
}

export function DriverOrderPaymentInfo({
    payment,
    className,
    compact = false,
}: DriverOrderPaymentInfoProps) {
    if (compact) {
        return (
            <div className={cn('space-y-1 text-sm', className)}>
                <PaymentRows payment={payment} compact />
            </div>
        );
    }

    return (
        <dl className={cn('space-y-2 text-sm', className)}>
            <PaymentRows payment={payment} compact={false} />
        </dl>
    );
}
