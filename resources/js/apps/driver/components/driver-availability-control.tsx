import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { StatusBadge } from '@/components/data-display/status-badge';
import { Button } from '@/components/ui/button';
import { useBrowserGeolocation } from '@/hooks/use-browser-geolocation';
import { cn } from '@/lib/utils';
import { update as updateAvailability } from '@/routes/driver/availability';

export type DriverAvailability =
    | 'offline'
    | 'available'
    | 'paused'
    | 'busy';

const detailLabels: Record<DriverAvailability, string> = {
    offline: 'desconectado',
    available: 'disponible',
    paused: 'en pausa',
    busy: 'en servicio',
};

type DriverAvailabilityControlProps = {
    availabilityStatus?: DriverAvailability;
    className?: string;
    compact?: boolean;
};

type DriverPageProps = {
    driver?: {
        availabilityStatus?: DriverAvailability;
        hasActiveOrders?: boolean;
    };
    errors?: Record<string, string>;
};

function resolveAvailabilityStatus(
    sharedStatus: DriverAvailability | undefined,
    propStatus: DriverAvailability | undefined,
): DriverAvailability {
    return sharedStatus ?? propStatus ?? 'offline';
}

function isConnected(status: DriverAvailability): boolean {
    return status !== 'offline';
}

export function DriverAvailabilityControl({
    availabilityStatus: availabilityStatusProp,
    className,
    compact = false,
}: DriverAvailabilityControlProps) {
    const { driver, errors } = usePage<DriverPageProps>().props;
    const availabilityStatus = resolveAvailabilityStatus(
        driver?.availabilityStatus,
        availabilityStatusProp,
    );
    const connected = isConnected(availabilityStatus);
    const hasActiveOrders = driver?.hasActiveOrders ?? false;
    const canDisconnect = connected && !hasActiveOrders;
    const detailLabel = detailLabels[availabilityStatus];
    const { loading, error, requestCurrentPosition } = useBrowserGeolocation();
    const [submitError, setSubmitError] = useState<string | null>(null);

    const toggleConnection = async () => {
        setSubmitError(null);

        if (connected && hasActiveOrders) {
            return;
        }

        const next: DriverAvailability = connected ? 'offline' : 'available';

        const payload: Record<string, string | number> = {
            availability_status: next,
        };

        if (next === 'available') {
            const point = await requestCurrentPosition();

            if (point) {
                payload.latitude = point.lat;
                payload.longitude = point.lng;
            }
        }

        router.patch(updateAvailability.url(), payload, {
            preserveScroll: true,
            onError: (pageErrors) => {
                const message =
                    pageErrors.availability_status ??
                    pageErrors.location ??
                    'No se pudo actualizar tu disponibilidad.';

                setSubmitError(message);
            },
        });
    };

    const feedbackError =
        submitError ?? errors?.availability_status ?? errors?.location ?? error;

    if (compact) {
        return (
            <StatusBadge tone={connected ? 'success' : 'neutral'}>
                {connected ? 'Conectado' : 'Desconectado'}
            </StatusBadge>
        );
    }

    return (
        <div
            className={cn(
                'rounded-xl border border-border bg-surface p-4 shadow-sm',
                className,
            )}
        >
            <p className="text-sm text-muted-foreground">Estado actual</p>
            <p className="mt-1 text-xl font-semibold text-navy">
                Estás {detailLabel}
            </p>
            <p className="mt-2 text-xs text-muted-foreground">
                La ubicación se usa para asignación y operación del servicio.
            </p>
            {feedbackError ? (
                <p className="mt-2 text-sm text-destructive">{feedbackError}</p>
            ) : null}
            {connected && hasActiveOrders ? (
                <p className="mt-2 text-sm text-muted-foreground">
                    Completa tus pedidos activos antes de desconectarte.
                </p>
            ) : null}
            <Button
                type="button"
                variant={connected ? 'default' : 'outline'}
                className={cn(
                    'mt-4 min-h-12 w-full',
                    canDisconnect &&
                        'border-success bg-success text-white hover:bg-success/90',
                )}
                disabled={loading || (connected && hasActiveOrders)}
                onClick={() => {
                    void toggleConnection();
                }}
            >
                {loading
                    ? 'Obteniendo ubicación…'
                    : connected
                      ? 'Desconectar'
                      : 'Conectarse'}
            </Button>
        </div>
    );
}
