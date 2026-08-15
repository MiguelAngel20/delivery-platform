import { useCallback, useState } from 'react';
import type { LatLng } from '@/lib/maps/types';

export type GeolocationErrorCode =
    | 'unsupported'
    | 'denied'
    | 'unavailable'
    | 'timeout'
    | 'unknown';

const messages: Record<GeolocationErrorCode, string> = {
    unsupported: 'Tu navegador no soporta geolocalización.',
    denied: 'Permiso de ubicación rechazado.',
    unavailable: 'No se pudo obtener la ubicación.',
    timeout: 'Se agotó el tiempo para obtener la ubicación.',
    unknown: 'No se pudo obtener la ubicación.',
};

export function useBrowserGeolocation() {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const requestCurrentPosition = useCallback(async (): Promise<LatLng | null> => {
        setError(null);

        if (!('geolocation' in navigator)) {
            setError(messages.unsupported);

            return null;
        }

        setLoading(true);

        try {
            const position = await new Promise<GeolocationPosition>((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(resolve, reject, {
                    enableHighAccuracy: true,
                    timeout: 12000,
                    maximumAge: 0,
                });
            });

            return {
                lat: position.coords.latitude,
                lng: position.coords.longitude,
            };
        } catch (err) {
            const geoError = err as GeolocationPositionError;
            let code: GeolocationErrorCode = 'unknown';

            if (geoError?.code === 1) {
                code = 'denied';
            } else if (geoError?.code === 2) {
                code = 'unavailable';
            } else if (geoError?.code === 3) {
                code = 'timeout';
            }

            setError(messages[code]);

            return null;
        } finally {
            setLoading(false);
        }
    }, []);

    return {
        loading,
        error,
        requestCurrentPosition,
        clearError: () => setError(null),
    };
}
