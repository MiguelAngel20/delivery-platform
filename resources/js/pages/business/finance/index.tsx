import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { StatCard } from '@/components/data-display/stat-card';
import { FormField } from '@/components/forms/form-field';
import { ContentCard, PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatMoney } from '@/lib/money';
import business from '@/routes/business';
import { index as financeIndex } from '@/routes/business/finance';

type FinanceOrder = {
    id: number;
    order_number: string;
    delivered_at: string | null;
    branch_name?: string | null;
    products_amount: string;
    service_fee: string;
    customer_total: string;
};

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

type Props = {
    summary: {
        completed_orders: number;
        products_amount: string;
        service_fee: string;
        customer_total: string;
    };
    orders: Paginated<FinanceOrder>;
    filters: {
        from: string;
        to: string;
    };
};

function formatDeliveredAt(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('es-MX', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

export default function BusinessFinanceIndex({
    summary,
    orders,
    filters,
}: Props) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [filtering, setFiltering] = useState(false);

    useEffect(() => {
        setFrom(filters.from);
        setTo(filters.to);
    }, [filters.from, filters.to]);

    const applyFilters = () => {
        setFiltering(true);
        router.get(
            financeIndex.url({
                query: { from, to },
            }),
            {},
            {
                preserveState: true,
                replace: true,
                onFinish: () => setFiltering(false),
            },
        );
    };

    return (
        <>
            <Head title="Finanzas" />
            <PageContainer>
                <PageHeader
                    title="Finanzas"
                    description="Resumen operativo de pedidos entregados"
                />

                <ContentCard title="Filtros" className="mb-4">
                    <div className="grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                        <FormField label="Desde">
                            <Input
                                type="date"
                                value={from}
                                onChange={(event) =>
                                    setFrom(event.target.value)
                                }
                            />
                        </FormField>
                        <FormField label="Hasta">
                            <Input
                                type="date"
                                value={to}
                                onChange={(event) => setTo(event.target.value)}
                            />
                        </FormField>
                        <div className="flex items-end">
                            <Button
                                type="button"
                                loading={filtering}
                                onClick={applyFilters}
                            >
                                Filtrar
                            </Button>
                        </div>
                    </div>
                </ContentCard>

                <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Pedidos completados"
                        value={summary.completed_orders}
                    />
                    <StatCard
                        title="Monto de productos"
                        value={formatMoney(summary.products_amount)}
                    />
                    <StatCard
                        title="Servicio asociado"
                        value={formatMoney(summary.service_fee)}
                    />
                    <StatCard
                        title="Total generado"
                        value={formatMoney(summary.customer_total)}
                    />
                </div>

                <ContentCard title="Pedidos entregados" bodyClassName="p-0">
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="border-b border-border bg-muted/40 text-left text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Pedido
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Fecha
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Sucursal
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Productos
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Servicio
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Total cliente
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {orders.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-4 py-6 text-muted-foreground"
                                        >
                                            No hay pedidos en el rango
                                            seleccionado.
                                        </td>
                                    </tr>
                                ) : (
                                    orders.data.map((order) => (
                                        <tr key={order.id}>
                                            <td className="px-4 py-3 font-medium text-foreground">
                                                #{order.order_number}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {formatDeliveredAt(
                                                    order.delivered_at,
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {order.branch_name ?? '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {formatMoney(
                                                    order.products_amount,
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {formatMoney(order.service_fee)}
                                            </td>
                                            <td className="px-4 py-3">
                                                {formatMoney(
                                                    order.customer_total,
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </ContentCard>
            </PageContainer>
        </>
    );
}

BusinessFinanceIndex.layout = {
    breadcrumbs: [
        { title: 'Business', href: business.home.url() },
        { title: 'Finanzas', href: financeIndex.url() },
    ],
};
