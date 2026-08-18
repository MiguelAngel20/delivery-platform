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

    interface PlacesLibrary {
        AutocompleteSuggestion: typeof places.AutocompleteSuggestion;
        AutocompleteSessionToken: typeof places.AutocompleteSessionToken;
        Place: typeof places.Place;
    }

    namespace places {
        class Place {
            id?: string;
            displayName?: string;
            formattedAddress?: string;
            location?: LatLng | LatLngLiteral | null;
            fetchFields(options: PlaceFetchFieldsRequest): Promise<{ place: Place }>;
        }

        class AutocompleteSuggestion {
            static fetchAutocompleteSuggestions(
                request: AutocompleteRequest,
            ): Promise<{ suggestions: AutocompleteSuggestionResult[] }>;
        }

        class AutocompleteSessionToken {}

        interface AutocompleteSuggestionResult {
            placePrediction?: PlacePrediction | null;
        }

        interface PlacePrediction {
            placeId: string;
            text: FormattableText;
            mainText?: FormattableText;
            secondaryText?: FormattableText;
            toPlace(): Place;
        }

        interface FormattableText {
            text: string;
        }

        interface AutocompleteRequest {
            input: string;
            sessionToken?: AutocompleteSessionToken;
            includedRegionCodes?: string[];
            locationBias?: LatLngBoundsLiteral;
            locationRestriction?: LatLngBoundsLiteral;
            origin?: LatLng | LatLngLiteral;
        }

        interface PlaceFetchFieldsRequest {
            fields: string[];
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

    interface LatLngBoundsLiteral {
        west: number;
        north: number;
        east: number;
        south: number;
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

    const event: {
        clearInstanceListeners(instance: object): void;
        trigger(instance: object, eventName: string): void;
    };

    const importLibrary: (
        library: 'places' | 'maps' | 'marker',
    ) => Promise<PlacesLibrary | unknown>;
}

declare const google: {
    maps: typeof google.maps;
};

declare global {
    interface Window {
        google?: typeof google;
        __rideGoogleMapsPromise?: Promise<typeof google>;
    }
}

export {};
