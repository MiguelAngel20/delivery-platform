import { Head } from '@inertiajs/react';
import { DriverDateRangeFilter } from '@/apps/driver/components/driver-date-range-filter';
import { StatCard } from '@/components/data-display/stat-card';
import { ContentCard, PageContainer } from '@/components/layout/page';
import { formatMoney } from '@/lib/money';
import { index as earningsIndex } from '@/routes/driver/earnings';

type EarningsOrder = {
    id: number;
    order_number: string;
    business_name: string;
    delivered_at: string | null;
    gross_earning: string;
    driver_commission: string;
    driver_earning: string;
    chisdrive_share: string;
    status_label: string;
};

type Props = {
    filters: {
        from: string;
        to: string;
    };
    summary: {
        today: string;
        week: string;
        range_commission: string;
        owed_to_chisdrive: string;
        completed_orders: number;
        pays_commission: boolean;
        commission_per_order: string;
    };
    orders: EarningsOrder[];
};

const summaryCardClassName =
    'gap-2 py-3 lg:gap-4 lg:py-5 [&_[data-slot=card-description]]:text-[11px] lg:[&_[data-slot=card-description]]:text-sm [&_[data-slot=card-header]]:gap-0 [&_[data-slot=card-header]]:px-3 lg:[&_[data-slot=card-header]]:px-5 [&_[data-slot=card-title]]:text-xl lg:[&_[data-slot=card-title]]:text-3xl';

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('es-MX', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

export default function DriverEarningsIndex({
    filters,
    summary,
    orders,
}: Props) {
    return (
        <>
            <Head title="Ganancias" />
            <PageContainer className="gap-4 px-4 py-4 md:px-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight text-navy">
                        Ganancias
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Pedidos entregados
                        {summary.pays_commission
                            ? ` · Comisión ChisDrive: ${formatMoney(summary.commission_per_order)} por pedido`
                            : ''}
                    </p>
                </div>

                <ContentCard title="Filtro de fechas">
                    <DriverDateRangeFilter
                        filters={filters}
                        actionUrl={(query) => earningsIndex.url({ query })}
                    />
                </ContentCard>

                <div className="grid grid-cols-2 gap-2 lg:grid-cols-4 lg:gap-3">
                    <StatCard
                        title="Ganancias de hoy"
                        value={formatMoney(summary.today)}
                        className={summaryCardClassName}
                    />
                    <StatCard
                        title="Ganancias de la semana"
                        value={formatMoney(summary.week)}
                        className={summaryCardClassName}
                    />
                    <StatCard
                        title="Comisión acumulada"
                        value={formatMoney(summary.range_commission)}
                        className={summaryCardClassName}
                    />
                    <StatCard
                        title="Debes a ChisDrive"
                        value={formatMoney(summary.owed_to_chisdrive)}
                        className={summaryCardClassName}
                    />
                </div>

                <ContentCard title="Historial" bodyClassName="p-0">
                    {orders.length === 0 ? (
                        <p className="px-4 py-6 text-sm text-muted-foreground md:px-5">
                            Aún no hay ganancias en este rango.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {orders.map((order) => (
                                <li
                                    key={order.id}
                                    className="space-y-2 px-4 py-3 md:px-5"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0 space-y-0.5">
                                            <p className="text-sm font-medium text-navy">
                                                #{order.order_number}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {order.business_name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {formatDate(order.delivered_at)}{' '}
                                                · {order.status_label}
                                            </p>
                                        </div>
                                        <span className="shrink-0 text-sm font-semibold text-success">
                                            {formatMoney(order.driver_earning)}
                                        </span>
                                    </div>
                                    {Number(order.driver_commission) > 0 ? (
                                        <div className="rounded-lg bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
                                            <p>
                                                Bruto:{' '}
                                                {formatMoney(order.gross_earning)}
                                            </p>
                                            <p>
                                                ChisDrive:{' '}
                                                {formatMoney(
                                                    order.chisdrive_share,
                                                )}
                                            </p>
                                            <p className="font-medium text-navy">
                                                Tu ganancia:{' '}
                                                {formatMoney(
                                                    order.driver_earning,
                                                )}
                                            </p>
                                        </div>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    )}
                </ContentCard>
            </PageContainer>
        </>
    );
}
