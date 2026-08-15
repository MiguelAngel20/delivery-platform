declare namespace google.maps {
    class Map {
        constructor(el: HTMLElement, opts?: MapOptions);
        setCenter(latLng: LatLng | LatLngLiteral): void;
        setZoom(zoom: number): void;
        panTo(latLng: LatLng | LatLngLiteral): void;
        addListener(eventName: string, handler: (...args: any[]) => void): MapsEventListener;
    }

    class Marker {
        constructor(opts?: MarkerOptions);
        setMap(map: Map | null): void;
        setPosition(latLng: LatLng | LatLngLiteral): void;
        getPosition(): LatLng | undefined;
        addListener(eventName: string, handler: (...args: any[]) => void): MapsEventListener;
    }

    class LatLng {
        constructor(lat: number, lng: number);
        lat(): number;
        lng(): number;
    }

    class Geocoder {
        geocode(
            request: GeocoderRequest,
            callback: (results: GeocoderResult[] | null, status: string) => void,
        ): void;
    }

    namespace places {
        class Autocomplete {
            constructor(inputField: HTMLInputElement, opts?: AutocompleteOptions);
            getPlace(): PlaceResult;
            addListener(eventName: string, handler: (...args: any[]) => void): MapsEventListener;
        }
    }

    interface MapOptions {
        center?: LatLng | LatLngLiteral;
        zoom?: number;
        mapTypeControl?: boolean;
        streetViewControl?: boolean;
        fullscreenControl?: boolean;
        zoomControl?: boolean;
    }

    interface MarkerOptions {
        map?: Map;
        position?: LatLng | LatLngLiteral;
        draggable?: boolean;
        title?: string;
    }

    interface LatLngLiteral {
        lat: number;
        lng: number;
    }

    interface MapsEventListener {
        remove(): void;
    }

    interface GeocoderRequest {
        location?: LatLng | LatLngLiteral;
        placeId?: string;
    }

    interface GeocoderResult {
        formatted_address?: string;
        place_id?: string;
        geometry?: {
            location: LatLng;
        };
    }

    interface AutocompleteOptions {
        fields?: string[];
        componentRestrictions?: { country: string | string[] };
    }

    interface PlaceResult {
        formatted_address?: string;
        name?: string;
        place_id?: string;
        geometry?: {
            location: LatLng;
        };
    }

    const event: {
        clearInstanceListeners(instance: object): void;
    };
}

declare const google: {
    maps: typeof google.maps;
};
