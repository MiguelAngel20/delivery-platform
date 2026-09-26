import { useCallback, useState } from 'react';
import type { LatLng } from '@/lib/maps/types';

export type GeolocationErrorCode =
    | 'unsupported'
    | 'denied'
    | 'unavailable'
    | 'timeout'
    | 'unknown';

export type BrowserPosition = LatLng & {
    /** Horizontal accuracy in meters from the browser (null if unavailable). */
    accuracyMeters: number | null;
};

const messages: Record<GeolocationErrorCode, string> = {
    unsupported: 'Tu navegador no soporta geolocalización.',
    denied: 'Permiso de ubicación rechazado.',
    unavailable: 'No se pudo obtener la ubicación.',
    timeout: 'Se agotó el tiempo para obtener la ubicación.',
    unknown: 'No se pudo obtener la ubicación.',
};

/** Readings worse than this are usually IP/Wi‑Fi guesses (common on desktop). */
export const POOR_GEOLOCATION_ACCURACY_METERS = 150;

const WATCH_BUDGET_MS = 8000;
const DESIRED_ACCURACY_METERS = 50;
const WATCH_TIMEOUT_MS = 15000;

function mapGeoError(err: unknown): GeolocationErrorCode {
    const geoError = err as GeolocationPositionError;

    if (geoError?.code === 1) {
        return 'denied';
    }

    if (geoError?.code === 2) {
        return 'unavailable';
    }

    if (geoError?.code === 3) {
        return 'timeout';
    }

    return 'unknown';
}

function toBrowserPosition(position: GeolocationPosition): BrowserPosition {
    const accuracy = position.coords.accuracy;

    return {
        lat: position.coords.latitude,
        lng: position.coords.longitude,
        accuracyMeters:
            typeof accuracy === 'number' && Number.isFinite(accuracy)
                ? accuracy
                : null,
    };
}

function getCurrentPositionOnce(
    options: PositionOptions,
): Promise<GeolocationPosition> {
    return new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(resolve, reject, options);
    });
}

/**
 * Prefer a short watchPosition window so desktop Wi‑Fi / mobile GPS can
 * settle on a better fix instead of the first coarse IP estimate.
 */
function watchBestPosition(): Promise<GeolocationPosition> {
    return new Promise((resolve, reject) => {
        let best: GeolocationPosition | null = null;
        let settled = false;

        const finish = (position: GeolocationPosition) => {
            if (settled) {
                return;
            }

            settled = true;
            window.clearTimeout(hardStop);
            navigator.geolocation.clearWatch(watchId);
            resolve(position);
        };

        const fail = (err: GeolocationPositionError) => {
            if (settled) {
                return;
            }

            settled = true;
            window.clearTimeout(hardStop);
            navigator.geolocation.clearWatch(watchId);
            reject(err);
        };

        const watchId = navigator.geolocation.watchPosition(
            (position) => {
                if (
                    best === null
                    || position.coords.accuracy < best.coords.accuracy
                ) {
                    best = position;
                }

                if (position.coords.accuracy <= DESIRED_ACCURACY_METERS) {
                    finish(position);
                }
            },
            fail,
            {
                enableHighAccuracy: true,
                maximumAge: 0,
                timeout: WATCH_TIMEOUT_MS,
            },
        );

        const hardStop = window.setTimeout(() => {
            if (best !== null) {
                finish(best);

                return;
            }

            fail({
                code: 3,
                message: 'Timeout',
                PERMISSION_DENIED: 1,
                POSITION_UNAVAILABLE: 2,
                TIMEOUT: 3,
            } as GeolocationPositionError);
        }, WATCH_BUDGET_MS);
    });
}

export function useBrowserGeolocation() {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const requestCurrentPosition =
        useCallback(async (): Promise<BrowserPosition | null> => {
            setError(null);

            if (!('geolocation' in navigator)) {
                setError(messages.unsupported);

                return null;
            }

            setLoading(true);

            try {
                try {
                    return toBrowserPosition(await watchBestPosition());
                } catch {
                    // Fall back to a single read if watchPosition fails early.
                    return toBrowserPosition(
                        await getCurrentPositionOnce({
                            enableHighAccuracy: true,
                            timeout: WATCH_TIMEOUT_MS,
                            maximumAge: 0,
                        }),
                    );
                }
            } catch (err) {
                setError(messages[mapGeoError(err)]);

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
