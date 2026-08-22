import type { LatLng } from '@/lib/maps/types';

/** Required by AdvancedMarkerElement. Demo ID is valid for production maps without a custom Cloud style. */
export const GOOGLE_MAPS_MAP_ID = 'DEMO_MAP_ID';

export function createAdvancedMarker(
    googleApi: typeof google,
    options: {
        map: google.maps.Map;
        position: google.maps.LatLngLiteral;
        title?: string;
        draggable?: boolean;
    },
): google.maps.marker.AdvancedMarkerElement {
    return new googleApi.maps.marker.AdvancedMarkerElement({
        map: options.map,
        position: options.position,
        title: options.title,
        gmpDraggable: options.draggable ?? false,
    });
}

export function readAdvancedMarkerPosition(
    marker: google.maps.marker.AdvancedMarkerElement,
): LatLng | null {
    const position = marker.position;

    if (!position) {
        return null;
    }

    if (typeof (position as google.maps.LatLng).lat === 'function') {
        const latLng = position as google.maps.LatLng;

        return {
            lat: latLng.lat(),
            lng: latLng.lng(),
        };
    }

    const literal = position as google.maps.LatLngLiteral;

    if (typeof literal.lat !== 'number' || typeof literal.lng !== 'number') {
        return null;
    }

    return {
        lat: literal.lat,
        lng: literal.lng,
    };
}
