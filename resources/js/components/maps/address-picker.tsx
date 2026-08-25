import { Crosshair, MapPin, Maximize2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { FormField } from '@/components/forms/form-field';
import {
    AddressMapView,
    type AddressMapHandle,
} from '@/components/maps/address-map-view';
import { useGoogleMaps } from '@/components/maps/google-maps-provider';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
    showFullscreenAdjust?: boolean;
    mapHeightClassName?: string;
    disabled?: boolean;
};

type PlaceSuggestion = AddressAutocompleteSuggestion;

const SEARCH_DEBOUNCE_MS = 250;
const SELECTED_PLACE_ZOOM = 18;

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
    showFullscreenAdjust = true,
    mapHeightClassName = 'h-[min(50vh,24rem)] md:h-96',
    disabled = false,
}: AddressPickerProps) {
    const { ready, error: mapsError, defaultCenter, google: googleApi } =
        useGoogleMaps();
    const { loading: geoLoading, error: geoError, requestCurrentPosition } =
        useBrowserGeolocation();

    const inlineMapRef = useRef<AddressMapHandle | null>(null);
    const fullscreenMapRef = useRef<AddressMapHandle | null>(null);
    const searchBoxRef = useRef<HTMLDivElement | null>(null);
    const geocoderRef = useRef<google.maps.Geocoder | null>(null);
    const sessionTokenRef =
        useRef<google.maps.places.AutocompleteSessionToken | null>(null);
    const onChangeRef = useRef(onChange);
    const valueRef = useRef(value);
    const searchRef = useRef(value?.address_text ?? '');
    const referenceRef = useRef(value?.reference ?? '');

    const [search, setSearch] = useState(value?.address_text ?? '');
    const [reference, setReference] = useState(value?.reference ?? '');
    const [localError, setLocalError] = useState<string | null>(null);
    const [suggestions, setSuggestions] = useState<PlaceSuggestion[]>([]);
    const [highlightIndex, setHighlightIndex] = useState(0);
    const [fullscreenOpen, setFullscreenOpen] = useState(false);
    const skipPredictionsRef = useRef(false);

    onChangeRef.current = onChange;
    valueRef.current = value;
    searchRef.current = search;
    referenceRef.current = reference;

    const mapCenter = {
        lat: value?.latitude ?? defaultCenter.latitude,
        lng: value?.longitude ?? defaultCenter.longitude,
    };

    const emit = (
        partial: Partial<AddressValue> & { latitude: number; longitude: number },
    ) => {
        const currentValue = valueRef.current;
        const next: AddressValue = {
            address_text: partial.address_text ?? searchRef.current ?? '',
            formatted_address:
                partial.formatted_address ?? currentValue?.formatted_address ?? null,
            reference: partial.reference ?? referenceRef.current ?? null,
            latitude: partial.latitude,
            longitude: partial.longitude,
            place_id: partial.place_id ?? currentValue?.place_id ?? null,
            google_maps_url: googleMapsSearchUrl(
                partial.latitude,
                partial.longitude,
            ),
        };

        onChangeRef.current(next);
    };

    const reverseGeocode = (lat: number, lng: number) => {
        if (!geocoderRef.current) {
            if (!googleApi) {
                return;
            }

            geocoderRef.current = new googleApi.maps.Geocoder();
        }

        geocoderRef.current.geocode({ location: { lat, lng } }, (results, status) => {
            emit({
                latitude: lat,
                longitude: lng,
                address_text:
                    searchRef.current || `${lat.toFixed(5)}, ${lng.toFixed(5)}`,
            });

            if (status !== 'OK' || !results?.[0]) {
                return;
            }

            const result = results[0];
            const formatted = result.formatted_address ?? searchRef.current;

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

    const moveMapsTo = (lat: number, lng: number, zoom = SELECTED_PLACE_ZOOM) => {
        inlineMapRef.current?.recenter(lat, lng, zoom);
        fullscreenMapRef.current?.recenter(lat, lng, zoom);
    };

    const applyCoordinates = (
        lat: number,
        lng: number,
        partial?: Partial<AddressValue>,
    ) => {
        moveMapsTo(lat, lng);
        setLocalError(null);

        if (partial?.address_text) {
            setSearch(partial.address_text);
            setSuggestions([]);
            skipPredictionsRef.current = true;
            emit({
                latitude: lat,
                longitude: lng,
                ...partial,
            });

            return;
        }

        reverseGeocode(lat, lng);
    };

    const applyResolvedPlace = (
        place: google.maps.places.Place,
        fallback: string,
    ) => {
        const coordinates = place.location ? readLatLng(place.location) : null;

        if (!coordinates) {
            setLocalError('Selecciona una sugerencia válida.');

            return;
        }

        const formatted =
            place.formattedAddress ?? place.displayName ?? fallback;

        applyCoordinates(coordinates.lat, coordinates.lng, {
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

    const useCurrentLocation = async () => {
        const point = await requestCurrentPosition();

        if (!point) {
            return;
        }

        applyCoordinates(point.lat, point.lng);
    };

    const recenterOnSaved = () => {
        if (value?.latitude == null || value?.longitude == null) {
            return;
        }

        moveMapsTo(Number(value.latitude), Number(value.longitude));
    };

    const onMapCenterSettled = (lat: number, lng: number) => {
        reverseGeocode(lat, lng);
    };

    useEffect(() => {
        if (!ready || !googleApi) {
            return;
        }

        geocoderRef.current = new googleApi.maps.Geocoder();

        void createAutocompleteSessionToken().then((token) => {
            sessionTokenRef.current = token;
        });

        if (value?.latitude != null && value?.longitude != null) {
            emit({
                latitude: Number(value.latitude),
                longitude: Number(value.longitude),
                address_text: value.address_text ?? search,
                formatted_address: value.formatted_address,
                place_id: value.place_id,
                reference: value.reference,
            });
        }

        return () => {
            geocoderRef.current = null;
            sessionTokenRef.current = null;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [ready, googleApi]);

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
        if (!fullscreenOpen) {
            return;
        }

        const timer = window.setTimeout(() => {
            fullscreenMapRef.current?.triggerResize();

            if (value?.latitude != null && value?.longitude != null) {
                fullscreenMapRef.current?.recenter(
                    Number(value.latitude),
                    Number(value.longitude),
                    SELECTED_PLACE_ZOOM,
                );
            }
        }, 250);

        return () => window.clearTimeout(timer);
    }, [fullscreenOpen, value?.latitude, value?.longitude]);

    const wasFullscreenOpenRef = useRef(false);

    useEffect(() => {
        const wasOpen = wasFullscreenOpenRef.current;
        wasFullscreenOpenRef.current = fullscreenOpen;

        if (!wasOpen || fullscreenOpen) {
            return;
        }

        const timer = window.setTimeout(() => {
            inlineMapRef.current?.triggerResize();

            if (value?.latitude != null && value?.longitude != null) {
                inlineMapRef.current?.recenter(
                    Number(value.latitude),
                    Number(value.longitude),
                    SELECTED_PLACE_ZOOM,
                );
            }
        }, 250);

        return () => window.clearTimeout(timer);
    }, [fullscreenOpen, value?.latitude, value?.longitude]);

    const mapBlockInteraction = suggestions.length > 0;

    const searchField = (
        <FormField
            label="Buscar dirección"
            error={localError ?? mapsError ?? undefined}
        >
            <div ref={searchBoxRef} className="relative z-20">
                <Input
                    value={search}
                    disabled={disabled || !ready}
                    onChange={(event) => setSearch(event.target.value)}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();

                            if (suggestions[highlightIndex]) {
                                void selectSuggestion(
                                    suggestions[highlightIndex],
                                );
                            }

                            return;
                        }

                        if (
                            event.key === 'ArrowDown' &&
                            suggestions.length > 0
                        ) {
                            event.preventDefault();
                            setHighlightIndex((current) =>
                                current + 1 >= suggestions.length
                                    ? 0
                                    : current + 1,
                            );

                            return;
                        }

                        if (event.key === 'ArrowUp' && suggestions.length > 0) {
                            event.preventDefault();
                            setHighlightIndex((current) =>
                                current - 1 < 0
                                    ? suggestions.length - 1
                                    : current - 1,
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
                                    onMouseEnter={() =>
                                        setHighlightIndex(index)
                                    }
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
    );

    const mapHint = (
        <p className="text-xs text-muted-foreground">
            Mueve el mapa para colocar el pin en tu ubicación exacta.
        </p>
    );

    const locationActions = (
        <div className="flex flex-wrap gap-2">
            {showCurrentLocation ? (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="min-h-10 flex-1"
                    disabled={disabled || geoLoading || !ready}
                    onClick={() => void useCurrentLocation()}
                >
                    {geoLoading
                        ? 'Obteniendo ubicación…'
                        : 'Usar mi ubicación actual'}
                </Button>
            ) : null}
            {value?.latitude != null && value?.longitude != null ? (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="min-h-10"
                    disabled={disabled || !ready}
                    onClick={recenterOnSaved}
                    aria-label="Centrar mapa en la ubicación guardada"
                >
                    <Crosshair className="size-4" />
                    <span className="sr-only md:not-sr-only md:ml-1.5">
                        Centrar
                    </span>
                </Button>
            ) : null}
            {showFullscreenAdjust ? (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="min-h-10 md:hidden"
                    disabled={disabled || !ready}
                    onClick={() => setFullscreenOpen(true)}
                >
                    <Maximize2 className="size-4" />
                    Ajustar en mapa
                </Button>
            ) : null}
        </div>
    );

    return (
        <div className="space-y-3">
            {searchField}

            {mapHint}

            {ready && googleApi ? (
                <AddressMapView
                    ref={inlineMapRef}
                    googleApi={googleApi}
                    initialCenter={mapCenter}
                    initialZoom={
                        value?.latitude != null ? SELECTED_PLACE_ZOOM : defaultCenter.zoom ?? 14
                    }
                    disabled={disabled}
                    blockInteraction={mapBlockInteraction}
                    onCenterSettled={onMapCenterSettled}
                    className={mapHeightClassName}
                />
            ) : (
                <div
                    className={cn(
                        mapHeightClassName,
                        'rounded-xl border border-border bg-muted',
                    )}
                />
            )}

            {locationActions}

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

                            if (
                                value?.latitude != null &&
                                value?.longitude != null
                            ) {
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

            <Dialog open={fullscreenOpen} onOpenChange={setFullscreenOpen}>
                <DialogContent className="fixed inset-0 top-0 left-0 flex h-[100dvh] max-h-[100dvh] w-full max-w-none translate-x-0 translate-y-0 flex-col gap-3 rounded-none border-0 p-4 sm:rounded-none">
                    <DialogHeader className="shrink-0 text-left">
                        <DialogTitle>Ajustar ubicación</DialogTitle>
                    </DialogHeader>

                    <p className="shrink-0 text-xs text-muted-foreground">
                        Mueve el mapa. El pin central marca dónde entregaremos.
                    </p>

                    {ready && googleApi ? (
                        <AddressMapView
                            ref={fullscreenMapRef}
                            googleApi={googleApi}
                            initialCenter={mapCenter}
                            initialZoom={SELECTED_PLACE_ZOOM}
                            disabled={disabled}
                            onCenterSettled={onMapCenterSettled}
                            className="min-h-0 flex-1"
                        />
                    ) : null}

                    <DialogFooter className="shrink-0 sm:justify-stretch">
                        <Button
                            type="button"
                            className="min-h-12 w-full"
                            onClick={() => setFullscreenOpen(false)}
                        >
                            Confirmar ubicación
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
