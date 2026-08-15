import { Head, usePage } from '@inertiajs/react';
import {
    ActiveOrderCard
    
} from '@/apps/driver/components/active-order-card';
import type {DriverActiveOrder} from '@/apps/driver/components/active-order-card';
import {
    AddToRouteCard,
} from '@/apps/driver/components/add-to-route-card';
import {
    AvailableOrderCard
    
} from '@/apps/driver/components/available-order-card';
import type {DriverAvailableOrder} from '@/apps/driver/components/available-order-card';
import {
    DriverAvailabilityControl
    
} from '@/apps/driver/components/driver-availability-control';
import type {DriverAvailability} from '@/apps/driver/components/driver-availability-control';
import {
    RouteOrderList
    
} from '@/apps/driver/components/route-order-list';
import type {DriverRouteGroup} from '@/apps/driver/components/route-order-list';
import { StatCard } from '@/components/data-display/stat-card';
import { EmptyState } from '@/components/feedback/empty-state';
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
    availableOrders: DriverAvailableOrder[];
};

export default function DriverHome({
    availabilityStatus,
    stats,
    activeGroups,
    compatibleOrders,
    availableOrders,
}: Props) {
    const { auth, realtime } = usePage().props as {
        auth: Auth;
        realtime?: { driver_id?: number | null };
    };
    const firstName = auth.user?.name.split(' ')[0] ?? 'Repartidor';
    const flatActive = activeGroups.flatMap((group) => group.orders);
    const branchIds = activeGroups.map((group) => group.branch_id);
    const idleOffers =
        flatActive.length === 0 ? availableOrders : compatibleOrders;

    useDriverOrderEvents(realtime?.driver_id, {
        branchIds,
        userId: auth.user?.id,
        only: [
            'availabilityStatus',
            'stats',
            'activeGroups',
            'compatibleOrders',
            'availableOrders',
        ],
    });

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

                {compatibleOrders.length > 0 ? (
                    <section className="space-y-3">
                        <h2 className="font-semibold text-navy">
                            Agregar a tu ruta
                        </h2>
                        {compatibleOrders.map((order) => (
                            <AddToRouteCard key={order.id} order={order} />
                        ))}
                    </section>
                ) : null}

                {flatActive.length === 0 ? (
                    <section className="space-y-3">
                        <h2 className="font-semibold text-navy">Disponibles</h2>
                        {idleOffers.length > 0 ? (
                            idleOffers.map((order) => (
                                <AvailableOrderCard
                                    key={order.id}
                                    order={order}
                                />
                            ))
                        ) : (
                            <EmptyState title="No hay pedidos disponibles" />
                        )}
                    </section>
                ) : null}
            </PageContainer>
        </>
    );
}
