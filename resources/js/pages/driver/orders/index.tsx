import { Head, usePage } from '@inertiajs/react';
import {
    ActiveOrderCard
    
} from '@/apps/driver/components/active-order-card';
import type {DriverActiveOrder} from '@/apps/driver/components/active-order-card';
import {
    AvailableOrderCard
    
} from '@/apps/driver/components/available-order-card';
import type {DriverAvailableOrder} from '@/apps/driver/components/available-order-card';
import { EmptyState } from '@/components/feedback/empty-state';
import { PageContainer } from '@/components/layout/page';
import { useDriverOrderEvents } from '@/hooks/realtime/use-order-realtime';
import type { Auth } from '@/types';

type Props = {
    availableOrders: DriverAvailableOrder[];
    activeOrders: DriverActiveOrder[];
};

export default function DriverOrdersIndex({
    availableOrders,
    activeOrders,
}: Props) {
    const { auth, realtime } = usePage().props as {
        auth: Auth;
        realtime?: { driver_id?: number | null };
    };

    useDriverOrderEvents(realtime?.driver_id, {
        userId: auth.user?.id,
        only: ['availableOrders', 'activeOrders'],
    });

    return (
        <>
            <Head title="Pedidos" />
            <PageContainer className="gap-4 px-4 py-4 md:px-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight text-navy">
                        Pedidos
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Disponibles para ti
                    </p>
                </div>

                {activeOrders.length > 0 ? (
                    <section className="space-y-3">
                        <h2 className="font-semibold text-navy">Activos</h2>
                        {activeOrders.map((order) => (
                            <ActiveOrderCard key={order.id} order={order} />
                        ))}
                    </section>
                ) : null}

                <section className="space-y-3">
                    <h2 className="font-semibold text-navy">Disponibles</h2>
                    {availableOrders.length > 0 ? (
                        availableOrders.map((order) => (
                            <AvailableOrderCard key={order.id} order={order} />
                        ))
                    ) : (
                        <EmptyState title="No hay pedidos disponibles" />
                    )}
                </section>
            </PageContainer>
        </>
    );
}
