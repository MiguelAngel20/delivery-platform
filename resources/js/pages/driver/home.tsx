import { Head, usePage } from '@inertiajs/react';
import {
    ActiveOrderCard,
    type DriverActiveOrder,
} from '@/apps/driver/components/active-order-card';
import {
    AddToRouteCard,
} from '@/apps/driver/components/add-to-route-card';
import type { DriverAvailableOrder } from '@/apps/driver/components/available-order-card';
import {
    DriverAvailabilityControl,
    type DriverAvailability,
} from '@/apps/driver/components/driver-availability-control';
import {
    RouteOrderList,
    type DriverRouteGroup,
} from '@/apps/driver/components/route-order-list';
import { EmptyState } from '@/components/feedback/empty-state';
import { StatCard } from '@/components/data-display/stat-card';
import { PageContainer } from '@/components/layout/page';
import { useDriverOrderEvents } from '@/hooks/realtime/use-order-realtime';
import type { Auth } from '@/types';

type ActiveGroup = DriverRouteGroup & {
    orders: DriverActiveOrder[];
};

type Props = {
    availabilityStatus: DriverAvailability;
    stats: { active_orders: number };
    activeGroups: ActiveGroup[];
    compatibleOrders: DriverAvailableOrder[];
};

export default function DriverHome({
    availabilityStatus,
    stats,
    activeGroups,
    compatibleOrders,
}: Props) {
    const { auth, realtime } = usePage().props as {
        auth: Auth;
        realtime?: { driver_id?: number | null };
    };
    const firstName = auth.user?.name.split(' ')[0] ?? 'Repartidor';
    const flatActive = activeGroups.flatMap((group) => group.orders);
    const branchIds = activeGroups.map((group) => group.branch_id);

    useDriverOrderEvents(realtime?.driver_id, branchIds);

    return (
        <>
            <Head title="Inicio" />
            <PageContainer className="gap-4 px-4 py-4 md:px-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight text-navy">
                        Hola, {firstName}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Listo para tu siguiente entrega
                    </p>
                </div>

                <DriverAvailabilityControl
                    availabilityStatus={availabilityStatus}
                />

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <StatCard title="Activos" value={String(stats.active_orders)} />
                </div>

                {activeGroups.map((group) => (
                    <RouteOrderList key={group.branch_id} route={group} />
                ))}

                {flatActive.length > 0 ? (
                    flatActive.map((order) => (
                        <ActiveOrderCard key={order.id} order={order} />
                    ))
                ) : (
                    <EmptyState title="Sin pedidos activos" />
                )}

                {compatibleOrders.map((order) => (
                    <AddToRouteCard key={order.id} order={order} />
                ))}
            </PageContainer>
        </>
    );
}
