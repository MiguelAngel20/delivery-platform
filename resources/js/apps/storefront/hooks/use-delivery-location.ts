import { useCallback, useSyncExternalStore } from 'react';

const STORAGE_KEY = 'ride.storefront.location';
const listeners = new Set<() => void>();

export type DeliveryLocation = {
    label: string;
    detail: string;
    latitude?: number | null;
    longitude?: number | null;
    formatted_address?: string | null;
    place_id?: string | null;
    reference?: string | null;
};

const emptyLocation: DeliveryLocation = {
    label: '¿Dónde entregamos?',
    detail: 'Elige una ubicación para explorar',
    latitude: null,
    longitude: null,
};

let cachedRaw: string | null = null;
let cachedLocation: DeliveryLocation = emptyLocation;

function emit(): void {
    listeners.forEach((listener) => listener());
}

function readLocation(): DeliveryLocation {
    if (typeof window === 'undefined') {
        return emptyLocation;
    }

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);

        if (raw === cachedRaw) {
            return cachedLocation;
        }

        cachedRaw = raw;

        if (!raw) {
            cachedLocation = emptyLocation;

            return cachedLocation;
        }

        cachedLocation = JSON.parse(raw) as DeliveryLocation;

        return cachedLocation;
    } catch {
        cachedRaw = null;
        cachedLocation = emptyLocation;

        return emptyLocation;
    }
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

export function useDeliveryLocation() {
    const location = useSyncExternalStore(
        subscribe,
        readLocation,
        () => emptyLocation,
    );

    const setLocation = useCallback((next: DeliveryLocation) => {
        const raw = JSON.stringify(next);
        window.localStorage.setItem(STORAGE_KEY, raw);
        cachedRaw = raw;
        cachedLocation = next;
        emit();
    }, []);

    const clearLocation = useCallback(() => {
        window.localStorage.removeItem(STORAGE_KEY);
        cachedRaw = null;
        cachedLocation = emptyLocation;
        emit();
    }, []);

    const hasCoordinates =
        typeof location.latitude === 'number' &&
        typeof location.longitude === 'number';

    return { location, setLocation, clearLocation, hasCoordinates } as const;
}
