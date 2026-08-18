import { MapPin } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { FormField } from '@/components/forms/form-field';
import { useGoogleMaps } from '@/components/maps/google-maps-provider';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useBrowserGeolocation } from '@/hooks/use-browser-geolocation';
import { googleMapsSearchUrl } from '@/lib/maps/google-maps-url';
import {
    createAutocompleteSessionToken,
    fetchAddressSuggestions,
    resolvePlaceFromSuggestion,
    type AddressAutocompleteSuggestion,
} from '@/lib/maps/places-autocomplete';
import type { AddressValue } from '@/lib/maps/types';
import { cn } from '@/lib/utils';

type AddressPickerProps = {
    value?: Partial<AddressValue> | null;
    onChange: (value: AddressValue) => void;
    showReference?: boolean;
    showCurrentLocation?: boolean;
    mapHeightClassName?: string;
    disabled?: boolean;
};

type PlaceSuggestion = AddressAutocompleteSuggestion;

const SEARCH_DEBOUNCE_MS = 250;

function readLatLng(location: google.maps.LatLng | google.maps.LatLngLiteral): {
    lat: number;
    lng: number;
} | null {
    if (typeof (location as google.maps.LatLng).lat === 'function') {
        const latLng = location as google.maps.LatLng;

        return {
            lat: latLng.lat(),
            lng: latLng.lng(),
        };
    }

    const literal = location as google.maps.LatLngLiteral;

    if (typeof literal.lat !== 'number' || typeof literal.lng !== 'number') {
        return null;
    }

    return {
        lat: literal.lat,
        lng: literal.lng,
    };
}

export function AddressPicker({
    value,
    onChange,
    showReference = true,
    showCurrentLocation = false,
    mapHeightClassName = 'h-56',
    disabled = false,
}: AddressPickerProps) {
    const { ready, error: mapsError, defaultCenter, google: googleApi } =
        useGoogleMaps();
    const { loading: geoLoading, error: geoError, requestCurrentPosition } =
        useBrowserGeolocation();

    const mapNodeRef = useRef<HTMLDivElement | null>(null);
    const searchBoxRef = useRef<HTMLDivElement | null>(null);
    const mapRef = useRef<google.maps.Map | null>(null);
    const markerRef = useRef<google.maps.Marker | null>(null);
    const geocoderRef = useRef<google.maps.Geocoder | null>(null);
    const sessionTokenRef =
        useRef<google.maps.places.AutocompleteSessionToken | null>(null);

    const [search, setSearch] = useState(value?.address_text ?? '');
    const [reference, setReference] = useState(value?.reference ?? '');
    const [localError, setLocalError] = useState<string | null>(null);
    const [suggestions, setSuggestions] = useState<PlaceSuggestion[]>([]);
    const [highlightIndex, setHighlightIndex] = useState(0);
    const skipPredictionsRef = useRef(false);

    const emit = (partial: Partial<AddressValue> & { latitude: number; longitude: number }) => {
        const next: AddressValue = {
            address_text: partial.address_text ?? search ?? '',
            formatted_address: partial.formatted_address ?? value?.formatted_address ?? null,
            reference: partial.reference ?? reference ?? null,
            latitude: partial.latitude,
            longitude: partial.longitude,
            place_id: partial.place_id ?? value?.place_id ?? null,
            google_maps_url: googleMapsSearchUrl(partial.latitude, partial.longitude),
        };

        onChange(next);
    };

    const applyResolvedPlace = (
        place: google.maps.places.Place,
        fallback: string,
    ) => {
        const coordinates = place.location ? readLatLng(place.location) : null;

        if (!coordinates || !mapRef.current || !markerRef.current) {
            setLocalError('Selecciona una sugerencia válida.');

            return;
        }

        const { lat, lng } = coordinates;
        const formatted =
            place.formattedAddress ?? place.displayName ?? fallback;

        setSearch(formatted);
        setSuggestions([]);
        skipPredictionsRef.current = true;
        setLocalError(null);
        mapRef.current.setCenter({ lat, lng });
        markerRef.current.setPosition({ lat, lng });
        emit({
            latitude: lat,
            longitude: lng,
            address_text: formatted,
            formatted_address: formatted,
            place_id: place.id ?? null,
        });
    };

    const selectSuggestion = async (suggestion: PlaceSuggestion) => {
        try {
            const place = await resolvePlaceFromSuggestion(suggestion);
            applyResolvedPlace(place, suggestion.description);
            sessionTokenRef.current = await createAutocompleteSessionToken();
        } catch {
            setLocalError('No se pudo cargar esa dirección.');
        }
    };

    const reverseGeocode = (lat: number, lng: number) => {
        if (!geocoderRef.current) {
            return;
        }

        geocoderRef.current.geocode({ location: { lat, lng } }, (results, status) => {
            if (status !== 'OK' || !results?.[0]) {
                emit({
                    latitude: lat,
                    longitude: lng,
                    address_text: search || `${lat.toFixed(5)}, ${lng.toFixed(5)}`,
                });

                return;
            }

            const result = results[0];
            const formatted = result.formatted_address ?? search;

            setSearch(formatted);
            setSuggestions([]);
            skipPredictionsRef.current = true;
            emit({
                latitude: lat,
                longitude: lng,
                address_text: formatted,
                formatted_address: formatted,
                place_id: result.place_id ?? null,
            });
        });
    };

    useEffect(() => {
        if (!ready || !googleApi || !mapNodeRef.current || mapRef.current) {
            return;
        }

        const center = {
            lat: value?.latitude ?? defaultCenter.latitude,
            lng: value?.longitude ?? defaultCenter.longitude,
        };

        const map = new googleApi.maps.Map(mapNodeRef.current, {
            center,
            zoom: defaultCenter.zoom ?? 14,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: false,
            zoomControl: true,
        });

        const marker = new googleApi.maps.Marker({
            map,
            position: center,
            draggable: !disabled,
        });

        marker.addListener('dragend', () => {
            const position = marker.getPosition();

            if (!position) {
                return;
            }

            reverseGeocode(position.lat(), position.lng());
        });

        mapRef.current = map;
        markerRef.current = marker;
        geocoderRef.current = new googleApi.maps.Geocoder();

        void createAutocompleteSessionToken().then((token) => {
            sessionTokenRef.current = token;
        });

        const fitMap = window.setTimeout(() => {
            googleApi.maps.event.trigger(map, 'resize');
            map.setCenter(center);
        }, 250);

        if (value?.latitude && value?.longitude) {
            emit({
                latitude: value.latitude,
                longitude: value.longitude,
                address_text: value.address_text ?? search,
                formatted_address: value.formatted_address,
                place_id: value.place_id,
                reference: value.reference,
            });
        }

        return () => {
            window.clearTimeout(fitMap);

            if (markerRef.current) {
                googleApi.maps.event.clearInstanceListeners(markerRef.current);
                markerRef.current.setMap(null);
            }

            mapRef.current = null;
            markerRef.current = null;
            geocoderRef.current = null;
            sessionTokenRef.current = null;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [ready, googleApi, disabled]);

    useEffect(() => {
        if (!ready || disabled) {
            return;
        }

        if (skipPredictionsRef.current) {
            skipPredictionsRef.current = false;
            setSuggestions([]);

            return;
        }

        const query = search.trim();

        if (query.length < 3) {
            setSuggestions([]);

            return;
        }

        const timeout = window.setTimeout(() => {
            const sessionToken = sessionTokenRef.current;

            if (!sessionToken) {
                return;
            }

            void fetchAddressSuggestions(query, sessionToken)
                .then((nextSuggestions) => {
                    setSuggestions(nextSuggestions);
                    setHighlightIndex(0);
                })
                .catch(() => {
                    setSuggestions([]);
                });
        }, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timeout);
    }, [disabled, ready, search]);

    useEffect(() => {
        const onPointerDown = (event: MouseEvent) => {
            if (
                searchBoxRef.current &&
                event.target instanceof Node &&
                !searchBoxRef.current.contains(event.target)
            ) {
                setSuggestions([]);
            }
        };

        document.addEventListener('mousedown', onPointerDown);

        return () => document.removeEventListener('mousedown', onPointerDown);
    }, []);

    useEffect(() => {
        if (!mapRef.current || !markerRef.current || value?.latitude == null || value?.longitude == null) {
            return;
        }

        const next = { lat: Number(value.latitude), lng: Number(value.longitude) };
        markerRef.current.setPosition(next);
        mapRef.current.panTo(next);
    }, [value?.latitude, value?.longitude]);

    return (
        <div className="space-y-3">
            <FormField label="Buscar dirección" error={localError ?? mapsError ?? undefined}>
                <div ref={searchBoxRef} className="relative z-20">
                    <Input
                        value={search}
                        disabled={disabled || !ready}
                        onChange={(event) => setSearch(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                event.preventDefault();

                                if (suggestions[highlightIndex]) {
                                    void selectSuggestion(suggestions[highlightIndex]);
                                }

                                return;
                            }

                            if (event.key === 'ArrowDown' && suggestions.length > 0) {
                                event.preventDefault();
                                setHighlightIndex((current) =>
                                    current + 1 >= suggestions.length ? 0 : current + 1,
                                );

                                return;
                            }

                            if (event.key === 'ArrowUp' && suggestions.length > 0) {
                                event.preventDefault();
                                setHighlightIndex((current) =>
                                    current - 1 < 0 ? suggestions.length - 1 : current - 1,
                                );

                                return;
                            }

                            if (event.key === 'Escape') {
                                setSuggestions([]);
                            }
                        }}
                        placeholder="Calle, colonia, ciudad"
                        autoComplete="off"
                    />
                    {suggestions.length > 0 ? (
                        <ul
                            role="listbox"
                            className="absolute z-[200] mt-1 max-h-64 w-full overflow-auto rounded-md border border-border bg-popover py-1 shadow-lg"
                        >
                            {suggestions.map((suggestion, index) => (
                                <li key={suggestion.placeId} role="option">
                                    <button
                                        type="button"
                                        className={cn(
                                            'flex w-full items-start gap-2 px-3 py-2 text-left text-sm',
                                            index === highlightIndex
                                                ? 'bg-accent'
                                                : 'hover:bg-accent',
                                        )}
                                        onMouseEnter={() => setHighlightIndex(index)}
                                        onPointerDown={(event) => {
                                            event.preventDefault();
                                            event.stopPropagation();
                                            void selectSuggestion(suggestion);
                                        }}
                                    >
                                        <MapPin className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                        <span className="min-w-0">
                                            <span className="block font-medium text-foreground">
                                                {suggestion.mainText}
                                            </span>
                                            {suggestion.secondaryText ? (
                                                <span className="block truncate text-xs text-muted-foreground">
                                                    {suggestion.secondaryText}
                                                </span>
                                            ) : null}
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    ) : null}
                </div>
            </FormField>

            <div
                ref={mapNodeRef}
                className={cn(
                    mapHeightClassName,
                    'relative z-0 w-full overflow-hidden rounded-xl border border-border bg-muted',
                    suggestions.length > 0 && 'pointer-events-none',
                )}
            />

            {showCurrentLocation ? (
                <Button
                    type="button"
                    variant="outline"
                    className="w-full"
                    disabled={disabled || geoLoading || !ready}
                    onClick={async () => {
                        const point = await requestCurrentPosition();

                        if (!point || !mapRef.current || !markerRef.current) {
                            return;
                        }

                        mapRef.current.setCenter(point);
                        markerRef.current.setPosition(point);
                        reverseGeocode(point.lat, point.lng);
                    }}
                >
                    {geoLoading ? 'Obteniendo ubicación…' : 'Usar mi ubicación actual'}
                </Button>
            ) : null}

            {geoError ? (
                <p className="text-sm text-destructive">{geoError}</p>
            ) : null}

            {showReference ? (
                <FormField label="Referencia">
                    <Textarea
                        value={reference ?? ''}
                        disabled={disabled}
                        rows={2}
                        placeholder="Casa azul, portón negro…"
                        onChange={(event) => {
                            const next = event.target.value;
                            setReference(next);

                            if (value?.latitude != null && value?.longitude != null) {
                                emit({
                                    latitude: Number(value.latitude),
                                    longitude: Number(value.longitude),
                                    reference: next,
                                    address_text: value.address_text ?? search,
                                });
                            }
                        }}
                    />
                </FormField>
            ) : null}
        </div>
    );
}
