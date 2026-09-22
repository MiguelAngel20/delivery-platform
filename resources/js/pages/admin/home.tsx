import { Head, Link } from '@inertiajs/react';
import {
    ClipboardList,
    Gift,
    Package,
    ShieldAlert,
    Users,
} from 'lucide-react';
import { StatCard } from '@/components/data-display/stat-card';
import { StatusBadge } from '@/components/data-display/status-badge';
import {
    ContentCard,
    PageContainer,
    PageHeader,
    Section,
} from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { useAdminCustomOrderEvents } from '@/hooks/realtime/use-order-realtime';
import { useAdminOrderEvents } from '@/hooks/realtime/use-order-realtime';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';
import { show as showCustomer } from '@/routes/admin/customers';
import { show as showOrder } from '@/routes/admin/orders';

type LaunchCustomer = {
    customer_id: number;
    name: string | null;
    email: string | null;
    phone: string | null;
    status: string;
    status_label: string;
    order_id: number | null;
    order_number: string | null;
    order_status: string | null;
    order_status_label: string | null;
    service_fee_discount: string | null;
    claimed_at: string | null;
};

type LoyaltyLaunch = {
    max_customers: number;
    consumed: number;
    reserved: number;
    used: number;
    remaining: number;
    progress_ratio: number;
    service_fee_percent: number;
    customers: LaunchCustomer[];
};

type Props = {
    operation: {
        pending_platform: number;
        custom_pending: number;
        quotes_waiting: number;
        open_incidents: number;
    };
    loyaltyLaunch: LoyaltyLaunch;
};

function formatClaimedAt(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('es-MX', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

export default function AdminHome({ operation, loyaltyLaunch }: Props) {
    useAdminOrderEvents(true, ['operation']);
    useAdminCustomOrderEvents(['operation']);

    const progressPercent = Math.round(
        Math.min(1, Math.max(0, loyaltyLaunch.progress_ratio)) * 100,
    );

    return (
        <>
            <Head title="Dashboard" />
            <PageContainer>
                <PageHeader
                    title="Operación"
                    description="Pedidos y solicitudes que requieren acción"
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Comandas ChisDrive pendientes"
                        value={String(operation.pending_platform)}
                        icon={<Package />}
                    />
                    <StatCard
                        title="Solicitudes personalizadas"
                        value={String(operation.custom_pending)}
                        icon={<ClipboardList />}
                    />
                    <StatCard
                        title="Cotizaciones esperando cliente"
                        value={String(operation.quotes_waiting)}
                        icon={<ClipboardList />}
                    />
                    <StatCard
                        title="Incidencias abiertas"
                        value={String(operation.open_incidents)}
                        icon={<ShieldAlert />}
                    />
                </div>

                <div className="flex flex-wrap gap-3">
                    <Button asChild>
                        <Link href={admin.orders.index()}>Ver pedidos</Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={admin.customOrders.index()}>
                            Ver personalizados
                        </Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={admin.incidents.index()}>
                            Ver incidencias
                        </Link>
                    </Button>
                </div>

                <Section
                    title="Bienvenida 50% (primeros clientes)"
                    description={`Cupos del descuento de lanzamiento (${loyaltyLaunch.service_fee_percent}% del service fee).`}
                >
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <StatCard
                            title="Cupos usados"
                            value={`${loyaltyLaunch.used}/${loyaltyLaunch.max_customers}`}
                            description={`${loyaltyLaunch.remaining} disponibles`}
                            icon={<Gift />}
                        />
                        <StatCard
                            title="Entregados"
                            value={String(loyaltyLaunch.consumed)}
                            description="Ya completaron el pedido de bienvenida"
                            icon={<Package />}
                        />
                        <StatCard
                            title="En curso"
                            value={String(loyaltyLaunch.reserved)}
                            description="Cupo reservado, pedido activo"
                            icon={<ClipboardList />}
                        />
                        <StatCard
                            title="Faltan"
                            value={String(loyaltyLaunch.remaining)}
                            description="Cupos libres del programa"
                            icon={<Users />}
                        />
                    </div>

                    <ContentCard
                        title="Progreso del programa"
                        description={`${loyaltyLaunch.used} de ${loyaltyLaunch.max_customers} cupos · ${progressPercent}%`}
                    >
                        <div
                            className="h-3 overflow-hidden rounded-full bg-border"
                            role="progressbar"
                            aria-valuenow={progressPercent}
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-label="Progreso cupos de bienvenida"
                        >
                            <div
                                className={cn(
                                    'h-full rounded-full bg-primary transition-[width] duration-500',
                                )}
                                style={{ width: `${progressPercent}%` }}
                            />
                        </div>
                    </ContentCard>

                    <ContentCard
                        title="Clientes con cupo de bienvenida"
                        description="Ordenados por quién reclamó el descuento primero"
                    >
                        {loyaltyLaunch.customers.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Aún no hay clientes con el descuento de
                                bienvenida.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[640px] text-left text-sm">
                                    <thead>
                                        <tr className="border-b border-border text-muted-foreground">
                                            <th className="pb-2 pr-3 font-medium">
                                                #
                                            </th>
                                            <th className="pb-2 pr-3 font-medium">
                                                Cliente
                                            </th>
                                            <th className="pb-2 pr-3 font-medium">
                                                Pedido
                                            </th>
                                            <th className="pb-2 pr-3 font-medium">
                                                Estado
                                            </th>
                                            <th className="pb-2 pr-3 font-medium">
                                                Descuento
                                            </th>
                                            <th className="pb-2 font-medium">
                                                Fecha
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {loyaltyLaunch.customers.map(
                                            (row, index) => (
                                                <tr
                                                    key={row.customer_id}
                                                    className="border-b border-border last:border-0"
                                                >
                                                    <td className="py-3 pr-3 text-muted-foreground">
                                                        {index + 1}
                                                    </td>
                                                    <td className="py-3 pr-3">
                                                        <Link
                                                            href={showCustomer.url(
                                                                row.customer_id,
                                                            )}
                                                            className="font-medium text-navy hover:underline"
                                                        >
                                                            {row.name ??
                                                                'Cliente'}
                                                        </Link>
                                                        <p className="text-xs text-muted-foreground">
                                                            {row.email ?? '—'}
                                                        </p>
                                                    </td>
                                                    <td className="py-3 pr-3">
                                                        {row.order_number ? (
                                                            <Link
                                                                href={showOrder.url(
                                                                    row.order_number,
                                                                )}
                                                                className="text-navy hover:underline"
                                                            >
                                                                #
                                                                {
                                                                    row.order_number
                                                                }
                                                            </Link>
                                                        ) : (
                                                            '—'
                                                        )}
                                                        {row.order_status_label ? (
                                                            <p className="text-xs text-muted-foreground">
                                                                {
                                                                    row.order_status_label
                                                                }
                                                            </p>
                                                        ) : null}
                                                    </td>
                                                    <td className="py-3 pr-3">
                                                        <StatusBadge
                                                            tone={
                                                                row.status ===
                                                                'delivered'
                                                                    ? 'success'
                                                                    : 'primary'
                                                            }
                                                        >
                                                            {row.status_label}
                                                        </StatusBadge>
                                                    </td>
                                                    <td className="py-3 pr-3 font-medium text-navy">
                                                        {row.service_fee_discount
                                                            ? formatMoney(
                                                                  row.service_fee_discount,
                                                              )
                                                            : '—'}
                                                    </td>
                                                    <td className="py-3 text-muted-foreground">
                                                        {formatClaimedAt(
                                                            row.claimed_at,
                                                        )}
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </ContentCard>
                </Section>
            </PageContainer>
        </>
    );
}

AdminHome.layout = {
    title: 'Dashboard',
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: admin.home(),
        },
    ],
};
