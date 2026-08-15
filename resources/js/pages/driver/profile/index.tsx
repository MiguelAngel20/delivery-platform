import { Head, Link, router, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { StatusBadge } from '@/components/data-display/status-badge';
import { ContentCard, PageContainer } from '@/components/layout/page';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useDriverProfileEvents } from '@/hooks/realtime/use-order-realtime';
import { deactivateStoredPushDevice } from '@/lib/push/devices';
import { logout } from '@/routes';
import type { Auth } from '@/types';

function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

type Props = {
    reputation: {
        average_rating: string | null;
        total_ratings: number;
        completed_orders: number;
        quality_label?: string | null;
    };
    phone?: string | null;
    scope_label?: string | null;
    approval_status_label?: string | null;
};

export default function DriverProfileIndex({
    reputation,
    phone,
    scope_label,
    approval_status_label,
}: Props) {
    const { auth, realtime } = usePage().props as {
        auth: Auth;
        realtime?: { driver_id?: number | null };
    };
    const name = auth.user?.name ?? 'Repartidor';
    const email = auth.user?.email ?? '';

    useDriverProfileEvents(realtime?.driver_id);

    return (
        <>
            <Head title="Perfil" />
            <PageContainer className="gap-4 px-4 py-4 md:px-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight text-navy">
                        Perfil
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Información básica
                    </p>
                </div>

                <ContentCard>
                    <div className="flex items-center gap-3">
                        <Avatar className="size-14">
                            <AvatarFallback className="bg-primary/10 text-primary">
                                {initials(name)}
                            </AvatarFallback>
                        </Avatar>
                        <div>
                            <p className="text-lg font-semibold text-navy">
                                {name}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {email}
                            </p>
                        </div>
                    </div>

                    <dl className="mt-6 space-y-4 text-sm">
                        <div className="flex items-center justify-between gap-3 border-b border-border pb-3">
                            <dt className="text-muted-foreground">Teléfono</dt>
                            <dd className="font-medium text-navy">
                                {phone ?? '—'}
                            </dd>
                        </div>
                        <div className="flex items-center justify-between gap-3 border-b border-border pb-3">
                            <dt className="text-muted-foreground">
                                Tipo de repartidor
                            </dt>
                            <dd className="font-medium text-navy">
                                {scope_label ?? '—'}
                            </dd>
                        </div>
                        <div className="flex items-center justify-between gap-3 border-b border-border pb-3">
                            <dt className="text-muted-foreground">
                                Estado de cuenta
                            </dt>
                            <dd>
                                <StatusBadge tone="success">
                                    {approval_status_label ?? 'Aprobado'}
                                </StatusBadge>
                            </dd>
                        </div>
                        <div className="flex items-center justify-between gap-3 border-b border-border pb-3">
                            <dt className="text-muted-foreground">
                                Calificación
                            </dt>
                            <dd className="font-semibold text-navy">
                                {reputation.average_rating
                                    ? `${reputation.average_rating} ★`
                                    : 'Sin calificaciones'}
                            </dd>
                        </div>
                        <div className="flex items-center justify-between gap-3 border-b border-border pb-3">
                            <dt className="text-muted-foreground">
                                Pedidos completados
                            </dt>
                            <dd className="font-semibold text-navy">
                                {reputation.completed_orders}
                            </dd>
                        </div>
                        {reputation.quality_label ? (
                            <div className="flex items-center justify-between gap-3">
                                <dt className="text-muted-foreground">
                                    Nivel de calidad
                                </dt>
                                <dd className="font-medium text-navy">
                                    {reputation.quality_label}
                                </dd>
                            </div>
                        ) : null}
                    </dl>
                </ContentCard>

                <Button asChild variant="outline" className="min-h-12 w-full">
                    <Link href="/driver/profile/notifications">
                        Notificaciones
                    </Link>
                </Button>

                <Button
                    asChild
                    variant="outline"
                    className="min-h-12 w-full text-destructive hover:bg-destructive/5 hover:text-destructive"
                >
                    <Link
                        href={logout()}
                        as="button"
                        data-test="driver-logout-button"
                        onClick={() => {
                            void deactivateStoredPushDevice();
                            router.flushAll();
                        }}
                    >
                        <LogOut className="size-4" />
                        Cerrar sesión
                    </Link>
                </Button>
            </PageContainer>
        </>
    );
}
