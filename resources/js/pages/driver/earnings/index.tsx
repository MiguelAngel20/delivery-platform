import { Head } from '@inertiajs/react';
import { StatCard } from '@/components/data-display/stat-card';
import { ContentCard, PageContainer } from '@/components/layout/page';
import { formatMoney } from '@/lib/money';

type EarningsOrder = {
    id: number;
    order_number: string;
    business_name: string;
    delivered_at: string | null;
    driver_earning: string;
    status_label: string;
};

type Props = {
    summary: {
        today: string;
        week: string;
        completed_orders: number;
    };
    orders: EarningsOrder[];
};

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('es-MX', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

export default function DriverEarningsIndex({ summary, orders }: Props) {
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
                    </p>
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    <StatCard title="Ganancias de hoy" value={formatMoney(summary.today)} />
                    <StatCard
                        title="Ganancias de la semana"
                        value={formatMoney(summary.week)}
                    />
                    <StatCard
                        title="Pedidos completados"
                        value={summary.completed_orders}
                    />
                </div>

                <ContentCard title="Historial" bodyClassName="p-0">
                    {orders.length === 0 ? (
                        <p className="px-4 py-6 text-sm text-muted-foreground md:px-5">
                            Aún no hay ganancias registradas.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {orders.map((order) => (
                                <li
                                    key={order.id}
                                    className="flex items-start justify-between gap-3 px-4 py-3 md:px-5"
                                >
                                    <div className="min-w-0 space-y-0.5">
                                        <p className="text-sm font-medium text-navy">
                                            #{order.order_number}
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {order.business_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {formatDate(order.delivered_at)} ·{' '}
                                            {order.status_label}
                                        </p>
                                    </div>
                                    <span className="shrink-0 text-sm font-semibold text-success">
                                        {formatMoney(order.driver_earning)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </ContentCard>
            </PageContainer>
        </>
    );
}
