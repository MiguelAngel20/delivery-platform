import { useState } from 'react';
import { PreparationTimeSelect } from '@/apps/business/components/preparation-time-select';
import type { MockActiveOrder } from '@/apps/business/mocks';
import { StatusBadge } from '@/components/data-display/status-badge';
import type { StatusTone } from '@/components/data-display/status-badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const statusTone: Record<MockActiveOrder['status'], StatusTone> = {
    Nuevo: 'info',
    Aceptado: 'primary',
    Preparando: 'warning',
    'Listo para recoger': 'success',
};

type ActiveOrderCardProps = {
    order: MockActiveOrder;
    className?: string;
};

export function ActiveOrderCard({ order, className }: ActiveOrderCardProps) {
    const [status, setStatus] = useState(order.status);
    const [showTimeSelect, setShowTimeSelect] = useState(
        order.status === 'Aceptado' || order.status === 'Preparando',
    );
    const [prepTime, setPrepTime] = useState(
        order.status === 'Preparando' ? '20' : '',
    );

    return (
        <article
            className={cn(
                'flex flex-col gap-4 rounded-xl border border-[#E2E8F0] bg-white p-4 text-[#0F172A] shadow-sm md:p-5',
                className,
            )}
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <h3 className="text-lg font-semibold tracking-tight">
                        Pedido #{order.code}
                    </h3>
                    <p className="text-sm text-[#64748B]">{order.time}</p>
                </div>
                <StatusBadge tone={statusTone[status]}>{status}</StatusBadge>
            </div>

            <div className="space-y-1">
                <p className="text-xs font-medium uppercase tracking-wide text-[#64748B]">
                    Cliente
                </p>
                <p className="text-sm font-medium">{order.customer}</p>
            </div>

            <ul className="space-y-1.5 border-y border-[#E2E8F0] py-3">
                {order.items.map((item) => (
                    <li
                        key={`${order.id}-${item.name}`}
                        className="flex justify-between gap-3 text-sm"
                    >
                        <span>
                            <span className="font-semibold text-[#FF7A00]">
                                {item.qty}x
                            </span>{' '}
                            {item.name}
                        </span>
                    </li>
                ))}
            </ul>

            {order.note ? (
                <div className="rounded-lg border border-[#FDE68A] bg-[#FFFBEB] px-3 py-2">
                    <p className="text-xs font-medium uppercase tracking-wide text-[#92400E]">
                        Nota
                    </p>
                    <p className="text-sm text-[#78350F]">{order.note}</p>
                </div>
            ) : null}

            <div className="flex items-center justify-between gap-3">
                <p className="text-xs font-medium uppercase tracking-wide text-[#64748B]">
                    Total
                </p>
                <p className="text-base font-semibold">{order.total}</p>
            </div>

            {showTimeSelect ? (
                <PreparationTimeSelect
                    value={prepTime}
                    onChange={(event) => setPrepTime(event.target.value)}
                />
            ) : null}

            <div className="flex flex-col gap-2 sm:flex-row">
                {status === 'Nuevo' ? (
                    <>
                        <Button
                            type="button"
                            className="min-h-11 flex-1"
                            onClick={() => {
                                setStatus('Aceptado');
                                setShowTimeSelect(true);
                            }}
                        >
                            Aceptar
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-11 flex-1"
                            onClick={() => undefined}
                        >
                            Rechazar
                        </Button>
                    </>
                ) : null}

                {status === 'Aceptado' ? (
                    <Button
                        type="button"
                        className="min-h-11 w-full"
                        disabled={!prepTime}
                        onClick={() => setStatus('Preparando')}
                    >
                        Preparando
                    </Button>
                ) : null}

                {status === 'Preparando' ? (
                    <Button
                        type="button"
                        className="min-h-11 w-full"
                        onClick={() => setStatus('Listo para recoger')}
                    >
                        Marcar listo
                    </Button>
                ) : null}

                {status === 'Listo para recoger' ? (
                    <p className="text-sm font-medium text-[#64748B]">
                        Listo para recoger
                    </p>
                ) : null}
            </div>
        </article>
    );
}
