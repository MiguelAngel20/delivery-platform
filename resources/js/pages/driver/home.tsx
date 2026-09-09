import { Head, usePage } from '@inertiajs/react';
import {
    CompletedOrderCard,
} from '@/apps/driver/components/completed-order-card';
import type { DriverCompletedOrder } from '@/apps/driver/components/completed-order-card';
import {
    DriverAvailabilityControl,
} from '@/apps/driver/components/driver-availability-control';
import {
    DriverDateRangeFilter,
} from '@/apps/driver/components/driver-date-range-filter';
import {
    DriverRatingCard,
} from '@/apps/driver/components/driver-rating-card';
import type { DriverReceivedRating } from '@/apps/driver/components/driver-rating-card';
import { EmptyState } from '@/components/feedback/empty-state';
import { ContentCard, PageContainer } from '@/components/layout/page';
import { useDriverOrderEvents } from '@/hooks/realtime/use-order-realtime';
import type { Auth } from '@/types';

type Props = {
    completedOrders: DriverCompletedOrder[];
    ratings: DriverReceivedRating[];
    filters: {
        from: string;
        to: string;
    };
};

export default function DriverHome({
    completedOrders,
    ratings,
    filters,
}: Props) {
    const { auth, realtime } = usePage().props as {
        auth: Auth;
        realtime?: { driver_id?: number | null };
    };
    const firstName = auth.user?.name.split(' ')[0] ?? 'Repartidor';
    const periodLabel =
        filters.from === filters.to
            ? `del ${new Date(`${filters.from}T12:00:00`).toLocaleDateString('es-MX', { dateStyle: 'long' })}`
            : 'del periodo seleccionado';

    useDriverOrderEvents(realtime?.driver_id, {
        userId: auth.user?.id,
        only: ['driver', 'completedOrders', 'ratings', 'filters'],
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
                        Resumen {periodLabel}
                    </p>
                </div>

                <DriverAvailabilityControl />

                <ContentCard title="Filtro de fechas">
                    <DriverDateRangeFilter filters={filters} />
                </ContentCard>

                <section className="space-y-3">
                    <h2 className="font-semibold text-navy">
                        Pedidos concluidos
                    </h2>
                    {completedOrders.length > 0 ? (
                        completedOrders.map((order) => (
                            <CompletedOrderCard key={order.id} order={order} />
                        ))
                    ) : (
                        <EmptyState title="No hay pedidos concluidos en este periodo" />
                    )}
                </section>

                <section className="space-y-3">
                    <h2 className="font-semibold text-navy">
                        Comentarios recibidos
                    </h2>
                    {ratings.length > 0 ? (
                        ratings.map((rating) => (
                            <DriverRatingCard key={rating.id} rating={rating} />
                        ))
                    ) : (
                        <EmptyState title="No hay comentarios en este periodo" />
                    )}
                </section>
            </PageContainer>
        </>
    );
}
