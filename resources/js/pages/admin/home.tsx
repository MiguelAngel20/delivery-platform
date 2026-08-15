import { Head, Link } from '@inertiajs/react';
import { ClipboardList, Package, ShieldAlert } from 'lucide-react';
import { StatCard } from '@/components/data-display/stat-card';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { useAdminCustomOrderEvents } from '@/hooks/realtime/use-order-realtime';
import { useAdminOrderEvents } from '@/hooks/realtime/use-order-realtime';
import admin from '@/routes/admin';

type Props = {
    operation: {
        pending_platform: number;
        custom_pending: number;
        quotes_waiting: number;
        open_incidents: number;
    };
};

export default function AdminHome({ operation }: Props) {
    useAdminOrderEvents(true, ['operation']);
    useAdminCustomOrderEvents(['operation']);

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
                        title="Pedidos RIDE pendientes"
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
                        <Link
                            href={admin.orders.index.url({
                                query: { filter: 'pending' },
                            })}
                        >
                            Ver pendientes
                        </Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={admin.customOrders.index()}>
                            Ver personalizados
                        </Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={admin.incidents.index()}>Ver incidencias</Link>
                    </Button>
                </div>
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
