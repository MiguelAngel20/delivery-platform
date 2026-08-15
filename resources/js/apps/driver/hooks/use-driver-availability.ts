import { useCallback, useSyncExternalStore } from 'react';
import type { DriverAvailability } from '@/apps/driver/mocks';
import {
    driverAvailabilityCycle,
    driverAvailabilityLabels,
} from '@/apps/driver/mocks';

const STORAGE_KEY = 'ride.driver.availability';
const listeners = new Set<() => void>();

function readAvailability(): DriverAvailability {
    if (typeof window === 'undefined') {
        return 'available';
    }

    const stored = window.sessionStorage.getItem(STORAGE_KEY);

    if (
        stored === 'offline' ||
        stored === 'available' ||
        stored === 'paused' ||
        stored === 'busy'
    ) {
        return stored;
    }

    return 'available';
}

function writeAvailability(value: DriverAvailability): void {
    window.sessionStorage.setItem(STORAGE_KEY, value);
    listeners.forEach((listener) => listener());
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

export function useDriverAvailability() {
    const availability = useSyncExternalStore(
        subscribe,
        readAvailability,
        () => 'available' as DriverAvailability,
    );

    const setAvailability = useCallback((value: DriverAvailability) => {
        writeAvailability(value);
    }, []);

    const cycleAvailability = useCallback(() => {
        const current = readAvailability();
        const index = driverAvailabilityCycle.indexOf(current);
        const next =
            driverAvailabilityCycle[
                (index + 1) % driverAvailabilityCycle.length
            ] ?? 'available';

        writeAvailability(next);
    }, []);

    return {
        availability,
        label: driverAvailabilityLabels[availability],
        setAvailability,
        cycleAvailability,
    } as const;
}
