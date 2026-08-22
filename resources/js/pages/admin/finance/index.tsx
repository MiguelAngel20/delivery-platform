import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { DataTable } from '@/components/data-display/data-table';
import type { DataTableColumn } from '@/components/data-display/data-table';
import { StatCard } from '@/components/data-display/stat-card';
import { FilterSelect } from '@/components/forms/filter-select';
import { FormField } from '@/components/forms/form-field';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatMoney } from '@/lib/money';
import admin from '@/routes/admin';
import { index as financeIndex, show as financeShow } from '@/routes/admin/finance';

type FinanceOrder = {
    id: number;
    order_number: string;
    delivered_at: string | null;
    business_name?: string | null;
    driver_name?: string | null;
    service_fee: string;
    driver_earning: string;
    business_amount: string;
    customer_total: string;
    payment_method_label: string;
    settlement_status?: string | null;
    settlement_status_label?: string | null;
};

type Paginated<T> = {
    data: T[];
};

type Option = { value: string; label: string };

type Props = {
    summary: {
        delivered_orders: number;
        service_income: string;
        driver_earnings: string;
        business_amount: string;
        pending_settlement: number;
    };
    orders: Paginated<FinanceOrder>;
    filters: {
        from: string;
        to: string;
        business_id: string;
        driver_id: string;
        settlement_status: string;
        payment_method: string;
    };
    filterOptions: {
        businesses: Option[];
        drivers: Option[];
        settlementStatuses: Option[];
        paymentMethods: Option[];
    };
};

const columns: DataTableColumn<FinanceOrder>[] = [
    {
        key: 'order_number',
        header: 'Pedido',
        cell: (row) => (
            <Link
                href={financeShow.url(row.order_number)}
                className="font-medium text-primary hover:underline"
            >
                #{row.order_number}
            </Link>
        ),
    },
    {
        key: 'delivered_at',
        header: 'Fecha',
        cell: (row) =>
            row.delivered_at
                ? new Date(row.delivered_at).toLocaleString('es-MX', {
                      dateStyle: 'short',
                      timeStyle: 'short',
                  })
                : '—',
    },
    {
        key: 'business_name',
        header: 'Empresa',
        cell: (row) => row.business_name ?? '—',
    },
    {
        key: 'driver_name',
        header: 'Repartidor',
        cell: (row) => row.driver_name ?? '—',
    },
    {
        key: 'business_amount',
        header: 'Negocio',
        cell: (row) => formatMoney(row.business_amount),
    },
    {
        key: 'driver_earning',
        header: 'Driver',
        cell: (row) => formatMoney(row.driver_earning),
    },
    {
        key: 'service_fee',
        header: 'Servicio',
        cell: (row) => formatMoney(row.service_fee),
    },
    {
        key: 'settlement_status_label',
        header: 'Conciliación',
        cell: (row) => row.settlement_status_label ?? '—',
    },
];

export default function AdminFinanceIndex({
    summary,
    orders,
    filters,
    filterOptions,
}: Props) {
    const [local, setLocal] = useState(filters);

    function applyFilters() {
        router.get(
            financeIndex.url({
                query: {
                    from: local.from || undefined,
                    to: local.to || undefined,
                    business_id: local.business_id || undefined,
                    driver_id: local.driver_id || undefined,
                    settlement_status: local.settlement_status || undefined,
                    payment_method: local.payment_method || undefined,
                },
            }),
            {},
            { preserveState: true },
        );
    }

    return (
        <>
            <Head title="Finanzas" />
            <PageContainer>
                <PageHeader
                    title="Finanzas"
                    description="Flujo económico operativo de pedidos entregados"
                />

                <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <StatCard
                        title="Pedidos entregados"
                        value={summary.delivered_orders}
                    />
                    <StatCard
                        title="Ingresos por servicio"
                        value={formatMoney(summary.service_income)}
                    />
                    <StatCard
                        title="Ganancias drivers"
                        value={formatMoney(summary.driver_earnings)}
                    />
                    <StatCard
                        title="Monto negocios"
                        value={formatMoney(summary.business_amount)}
                    />
                    <StatCard
                        title="Pendientes conciliación"
                        value={summary.pending_settlement}
                    />
                </div>

                <div className="mb-4 grid gap-3 rounded-xl border border-border bg-card p-4 text-card-foreground md:grid-cols-3 lg:grid-cols-7">
                    <FormField label="Desde">
                        <Input
                            type="date"
                            value={local.from}
                            onChange={(event) =>
                                setLocal((prev) => ({
                                    ...prev,
                                    from: event.target.value,
                                }))
                            }
                        />
                    </FormField>
                    <FormField label="Hasta">
                        <Input
                            type="date"
                            value={local.to}
                            onChange={(event) =>
                                setLocal((prev) => ({
                                    ...prev,
                                    to: event.target.value,
                                }))
                            }
                        />
                    </FormField>
                    <FilterSelect
                        label="Empresa"
                        value={local.business_id}
                        onChange={(event) =>
                            setLocal((prev) => ({
                                ...prev,
                                business_id: event.target.value,
                            }))
                        }
                    >
                        <option value="">Todas</option>
                        {filterOptions.businesses.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </FilterSelect>
                    <FilterSelect
                        label="Repartidor"
                        value={local.driver_id}
                        onChange={(event) =>
                            setLocal((prev) => ({
                                ...prev,
                                driver_id: event.target.value,
                            }))
                        }
                    >
                        <option value="">Todos</option>
                        {filterOptions.drivers.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </FilterSelect>
                    <FilterSelect
                        label="Conciliación"
                        value={local.settlement_status}
                        onChange={(event) =>
                            setLocal((prev) => ({
                                ...prev,
                                settlement_status: event.target.value,
                            }))
                        }
                    >
                        <option value="">Todos</option>
                        {filterOptions.settlementStatuses.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </FilterSelect>
                    <FilterSelect
                        label="Pago"
                        value={local.payment_method}
                        onChange={(event) =>
                            setLocal((prev) => ({
                                ...prev,
                                payment_method: event.target.value,
                            }))
                        }
                    >
                        <option value="">Todos</option>
                        {filterOptions.paymentMethods.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </FilterSelect>
                    <div className="flex items-end">
                        <Button type="button" onClick={applyFilters}>
                            Filtrar
                        </Button>
                    </div>
                </div>

                <DataTable
                    columns={columns}
                    data={orders.data}
                    rowKey={(row) => row.id}
                />
            </PageContainer>
        </>
    );
}

AdminFinanceIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: admin.home() },
        { title: 'Finanzas', href: financeIndex.url() },
    ],
};
