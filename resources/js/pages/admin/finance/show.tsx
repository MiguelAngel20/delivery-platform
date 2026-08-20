import { Head } from '@inertiajs/react';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { BackButton } from '@/components/navigation/back-button';
import { formatMoney } from '@/lib/money';
import admin from '@/routes/admin';
import { index as financeIndex } from '@/routes/admin/finance';

type FinancialDetail = {
    products_amount: string;
    service_fee: string;
    customer_total: string;
    business_amount: string;
    driver_earning: string;
    platform_earning: string;
    collection_party_label: string;
    settlement_status_label: string;
    payment_method_label: string;
    payment_status_label: string;
    transactions: Array<{
        id: number;
        transaction_type_label: string;
        amount: string;
        status_label: string;
        description?: string | null;
    }>;
};

type Props = {
    order: {
        order_number: string;
        restaurant: { name?: string | null };
        driver?: { name: string } | null;
    };
    financial: FinancialDetail | null;
};

export default function AdminFinanceShow({ order, financial }: Props) {
    return (
        <>
            <Head title={`Finanzas ${order.order_number}`} />
            <PageContainer>
                <PageHeader
                    title={`Order #${order.order_number}`}
                    description={`${order.restaurant.name ?? 'Negocio'} · ${order.driver?.name ?? 'Sin repartidor'}`}
                    actions={<BackButton href={financeIndex.url()} />}
                />

                {financial === null ? (
                    <p className="text-sm text-muted-foreground">
                        Este pedido no tiene snapshot financiero.
                    </p>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        <section className="space-y-3 rounded-xl border border-border bg-white p-4">
                            <h2 className="font-semibold text-navy">Snapshot</h2>
                            <dl className="space-y-2 text-sm">
                                <div className="flex justify-between gap-3">
                                    <dt>Products</dt>
                                    <dd>{formatMoney(financial.products_amount)}</dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt>Service</dt>
                                    <dd>{formatMoney(financial.service_fee)}</dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt>Customer Total</dt>
                                    <dd className="font-semibold">
                                        {formatMoney(financial.customer_total)}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-3 border-t border-border pt-2">
                                    <dt>Business Amount</dt>
                                    <dd>{formatMoney(financial.business_amount)}</dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt>Driver Earning</dt>
                                    <dd>{formatMoney(financial.driver_earning)}</dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt>Platform Earning</dt>
                                    <dd>{formatMoney(financial.platform_earning)}</dd>
                                </div>
                                <div className="flex justify-between gap-3 border-t border-border pt-2">
                                    <dt>Collection Party</dt>
                                    <dd>{financial.collection_party_label}</dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt>Settlement</dt>
                                    <dd>{financial.settlement_status_label}</dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt>Payment</dt>
                                    <dd>
                                        {financial.payment_method_label} ·{' '}
                                        {financial.payment_status_label}
                                    </dd>
                                </div>
                            </dl>
                        </section>

                        <section className="space-y-3 rounded-xl border border-border bg-white p-4">
                            <h2 className="font-semibold text-navy">Movimientos</h2>
                            {financial.transactions.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Sin movimientos registrados.
                                </p>
                            ) : (
                                <ul className="divide-y divide-border">
                                    {financial.transactions.map((tx) => (
                                        <li
                                            key={tx.id}
                                            className="flex items-start justify-between gap-3 py-3 text-sm"
                                        >
                                            <div>
                                                <p className="font-medium text-navy">
                                                    {tx.transaction_type_label}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {tx.status_label}
                                                    {tx.description
                                                        ? ` · ${tx.description}`
                                                        : ''}
                                                </p>
                                            </div>
                                            <span className="font-semibold">
                                                {formatMoney(tx.amount)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </div>
                )}
            </PageContainer>
        </>
    );
}

AdminFinanceShow.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin.home() },
        { title: 'Finanzas', href: financeIndex.url() },
        { title: 'Detalle', href: '#' },
    ],
};
