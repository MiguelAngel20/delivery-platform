import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState, type KeyboardEvent } from 'react';
import { StatusBadge } from '@/components/data-display/status-badge';
import { FormField } from '@/components/forms/form-field';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAdminOrderEvents } from '@/hooks/realtime/use-order-realtime';
import { formatMoney } from '@/lib/money';
import admin from '@/routes/admin';
import { inbox, show } from '@/routes/admin/orders';

type OrderRow = {
    order_number: string;
    order_status: string;
    business_status_label: string;
    total: string;
    created_at: string | null;
    customer: {
        name?: string | null;
        phone?: string | null;
        reputation_label?: string | null;
    };
    restaurant: { name?: string | null; branch_name?: string | null };
    items: Array<{
        product_name: string;
        quantity: string;
        options: Array<{ display: string }>;
        notes?: string | null;
    }>;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
};

type Props = {
    orders: Paginated<OrderRow>;
    newCount: number;
    filters: { search: string; status: string; from: string; to: string };
    statusOptions: Array<{ value: string; label: string }>;
};

function visitFilters(next: Partial<Props['filters']> & { page?: number }) {
    router.get(inbox.url({ query: next }), {}, { preserveState: true, replace: true });
}

const selectClassName =
    'border-input bg-background text-foreground focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50';

const dateInputClassName = 'bg-background scheme-light dark:scheme-dark';

export default function AdminOrdersInbox({
    orders,
    newCount,
    filters,
    statusOptions,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    useAdminOrderEvents(true, ['orders', 'newCount']);

    useEffect(() => {
        setFrom(filters.from);
        setTo(filters.to);
    }, [filters.from, filters.to]);

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            if (search !== filters.search) {
                visitFilters({ ...filters, search, page: 1 });
            }
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [search, filters.search, filters.status, filters.from, filters.to]);

    function applyFilters(page = 1) {
        visitFilters({
            ...filters,
            search,
            from,
            to,
            page,
        });
    }

    function handleDateKeyDown(event: KeyboardEvent<HTMLInputElement>) {
        if (event.key === 'Enter') {
            event.preventDefault();
            applyFilters();
        }
    }

    return (
        <>
            <Head title="Comandas ChisDrive" />
            <PageContainer>
                <PageHeader
                    title="Comandas ChisDrive"
                    description={
                        newCount > 0
                            ? `${newCount} nuevo${newCount === 1 ? '' : 's'} por aceptar`
                            : 'Pedidos de negocios administrados por ChisDrive'
                    }
                    actions={
                        <Button variant="outline" size="sm" asChild>
                            <Link href={admin.orders.index()}>Ver todos los pedidos</Link>
                        </Button>
                    }
                />

                <section className="mb-4 rounded-xl border border-border bg-surface p-4 shadow-sm">
                    <h2 className="mb-3 text-sm font-medium text-foreground">
                        Filtros
                    </h2>
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
                        <FormField label="Buscar">
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Pedido, cliente o negocio..."
                            />
                        </FormField>
                        <FormField label="Estado">
                            <select
                                className={selectClassName}
                                value={filters.status || ''}
                                onChange={(event) =>
                                    visitFilters({
                                        ...filters,
                                        search,
                                        from,
                                        to,
                                        status: event.target.value,
                                        page: 1,
                                    })
                                }
                            >
                                <option value="">Todos</option>
                                {statusOptions.map((status) => (
                                    <option
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                        <FormField label="Desde">
                            <Input
                                type="date"
                                value={from}
                                className={dateInputClassName}
                                onChange={(event) =>
                                    setFrom(event.target.value)
                                }
                                onKeyDown={handleDateKeyDown}
                            />
                        </FormField>
                        <FormField label="Hasta">
                            <Input
                                type="date"
                                value={to}
                                className={dateInputClassName}
                                onChange={(event) => setTo(event.target.value)}
                                onKeyDown={handleDateKeyDown}
                            />
                        </FormField>
                        <div className="flex items-end sm:col-span-2 xl:col-span-1">
                            <Button
                                type="button"
                                className="w-full xl:w-auto"
                                onClick={() => applyFilters()}
                            >
                                Filtrar
                            </Button>
                        </div>
                    </div>
                </section>

                {orders.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-border bg-surface/50 px-4 py-10 text-center">
                        <p className="font-medium text-foreground">
                            Sin comandas en este rango
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Los pedidos nuevos de negocios ChisDrive aparecerán
                            aquí en tiempo real.
                        </p>
                    </div>
                ) : null}

                <div className="space-y-3">
                    {orders.data.map((order) => (
                        <article
                            key={order.order_number}
                            className="rounded-xl border border-border bg-surface p-4 shadow-sm"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="font-semibold text-foreground">
                                            #{order.order_number}
                                        </h2>
                                        <StatusBadge
                                            tone={
                                                order.order_status ===
                                                'pending_platform'
                                                    ? 'warning'
                                                    : order.order_status ===
                                                        'preparing'
                                                      ? 'primary'
                                                      : order.order_status ===
                                                          'ready_for_pickup'
                                                        ? 'info'
                                                        : 'neutral'
                                            }
                                        >
                                            {order.business_status_label}
                                        </StatusBadge>
                                    </div>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {order.customer.name}
                                        {order.customer.reputation_label
                                            ? ` · ${order.customer.reputation_label}`
                                            : ''}
                                        {order.restaurant.name
                                            ? ` · ${order.restaurant.name}`
                                            : ''}
                                        {order.restaurant.branch_name
                                            ? ` · ${order.restaurant.branch_name}`
                                            : ''}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className="font-semibold text-foreground">
                                        {formatMoney(order.total)}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {order.created_at
                                            ? new Date(
                                                  order.created_at,
                                              ).toLocaleString()
                                            : ''}
                                    </p>
                                </div>
                            </div>

                            <ul className="mt-3 space-y-2 text-sm">
                                {order.items.map((item, index) => (
                                    <li key={`${order.order_number}-${index}`}>
                                        <p className="text-foreground">
                                            {item.quantity}x {item.product_name}
                                        </p>
                                        {item.options.length > 0 ? (
                                            <p className="text-xs text-muted-foreground">
                                                {item.options
                                                    .map((option) => option.display)
                                                    .join(' · ')}
                                            </p>
                                        ) : null}
                                        {item.notes ? (
                                            <p className="text-xs text-muted-foreground">
                                                Nota: {item.notes}
                                            </p>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>

                            <div className="mt-4 flex justify-end">
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={show.url(order.order_number)}>
                                        {order.order_status === 'pending_platform'
                                            ? 'Aceptar / Gestionar'
                                            : 'Ver / Gestionar'}
                                    </Link>
                                </Button>
                            </div>
                        </article>
                    ))}
                </div>
            </PageContainer>
        </>
    );
}

AdminOrdersInbox.layout = {
    title: 'Comandas',
    breadcrumbs: [
        {
            title: 'Comandas',
            href: admin.orders.inbox(),
        },
    ],
};
