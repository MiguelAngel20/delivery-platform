export type LatLng = {
    lat: number;
    lng: number;
};

export type AddressValue = {
    address_text: string;
    formatted_address?: string | null;
    reference?: string | null;
    latitude: number;
    longitude: number;
    place_id?: string | null;
    google_maps_url?: string | null;
};

export type MapsSharedConfig = {
    browser_api_key: string;
    default_center: {
        latitude: number;
        longitude: number;
        zoom: number;
    };
};

declare global {
    interface Window {
        google?: typeof google;
        __rideGoogleMapsPromise?: Promise<typeof google>;
    }
}

export {};
