import type { LatLng } from '@/lib/maps/types';

export function googleMapsSearchUrl(latitude: number | string, longitude: number | string): string {
    const lat = Number(latitude).toFixed(7);
    const lng = Number(longitude).toFixed(7);

    return `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;
}

export function formatDistanceKm(meters: number): string {
    if (meters < 1000) {
        return `${Math.round(meters)} m`;
    }

    return `${(meters / 1000).toFixed(1)} km`;
}

export function toLatLng(latitude: number | string, longitude: number | string): LatLng {
    return {
        lat: Number(latitude),
        lng: Number(longitude),
    };
}
