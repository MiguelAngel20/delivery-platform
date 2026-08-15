import { router } from '@inertiajs/react';
import { StatusBadge } from '@/components/data-display/status-badge';
import type { StatusTone } from '@/components/data-display/status-badge';
import { Button } from '@/components/ui/button';
import { useBrowserGeolocation } from '@/hooks/use-browser-geolocation';
import { cn } from '@/lib/utils';
import { update as updateAvailability } from '@/routes/driver/availability';

export type DriverAvailability =
    | 'offline'
    | 'available'
    | 'paused'
    | 'busy';

const labels: Record<DriverAvailability, string> = {
    offline: 'Desconectado',
    available: 'Disponible',
    paused: 'En pausa',
    busy: 'En servicio',
};

const cycle: DriverAvailability[] = [
    'available',
    'paused',
    'busy',
    'offline',
];

const availabilityTone: Record<DriverAvailability, StatusTone> = {
    offline: 'neutral',
    available: 'success',
    paused: 'warning',
    busy: 'primary',
};

type DriverAvailabilityControlProps = {
    availabilityStatus?: DriverAvailability;
    className?: string;
    compact?: boolean;
};

export function DriverAvailabilityControl({
    availabilityStatus = 'available',
    className,
    compact = false,
}: DriverAvailabilityControlProps) {
    const label = labels[availabilityStatus];
    const { loading, error, requestCurrentPosition } = useBrowserGeolocation();

    const cycleAvailability = async () => {
        const currentIndex = cycle.indexOf(availabilityStatus);
        const next = cycle[(currentIndex + 1) % cycle.length] ?? 'available';

        const payload: Record<string, string | number> = {
            availability_status: next,
        };

        if (next === 'available' || next === 'busy') {
            const point = await requestCurrentPosition();

            if (point) {
                payload.latitude = point.lat;
                payload.longitude = point.lng;
            }
        }

        router.patch(updateAvailability.url(), payload);
    };

    if (compact) {
        return (
            <StatusBadge tone={availabilityTone[availabilityStatus]}>
                {label}
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
                Estás {label.toLowerCase()}
            </p>
            <p className="mt-2 text-xs text-muted-foreground">
                La ubicación se usa para asignación y operación del servicio.
            </p>
            {error ? (
                <p className="mt-2 text-sm text-destructive">{error}</p>
            ) : null}
            <Button
                type="button"
                variant="outline"
                className="mt-4 min-h-12 w-full"
                disabled={loading}
                onClick={() => {
                    void cycleAvailability();
                }}
            >
                {loading ? 'Obteniendo ubicación…' : 'Cambiar disponibilidad'}
            </Button>
        </div>
    );
}
