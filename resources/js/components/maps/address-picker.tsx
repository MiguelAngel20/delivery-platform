import { useEffect, useRef, useState } from 'react';
import { FormField } from '@/components/forms/form-field';
import { useGoogleMaps } from '@/components/maps/google-maps-provider';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useBrowserGeolocation } from '@/hooks/use-browser-geolocation';
import { googleMapsSearchUrl } from '@/lib/maps/google-maps-url';
import type { AddressValue } from '@/lib/maps/types';

type AddressPickerProps = {
    value?: Partial<AddressValue> | null;
    onChange: (value: AddressValue) => void;
    showReference?: boolean;
    showCurrentLocation?: boolean;
    mapHeightClassName?: string;
    disabled?: boolean;
};

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
    const searchInputRef = useRef<HTMLInputElement | null>(null);
    const mapRef = useRef<google.maps.Map | null>(null);
    const markerRef = useRef<google.maps.Marker | null>(null);
    const autocompleteRef = useRef<google.maps.places.Autocomplete | null>(null);
    const geocoderRef = useRef<google.maps.Geocoder | null>(null);

    const [search, setSearch] = useState(value?.address_text ?? '');
    const [reference, setReference] = useState(value?.reference ?? '');
    const [localError, setLocalError] = useState<string | null>(null);

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

        if (searchInputRef.current) {
            const autocomplete = new googleApi.maps.places.Autocomplete(
                searchInputRef.current,
                {
                    fields: ['formatted_address', 'geometry', 'place_id', 'name'],
                    componentRestrictions: { country: 'mx' },
                },
            );

            autocomplete.addListener('place_changed', () => {
                const place = autocomplete.getPlace();
                const location = place.geometry?.location;

                if (!location) {
                    setLocalError('Selecciona una sugerencia válida.');

                    return;
                }

                const lat = location.lat();
                const lng = location.lng();
                const formatted = place.formatted_address ?? place.name ?? search;

                setSearch(formatted);
                setLocalError(null);
                map.setCenter({ lat, lng });
                marker.setPosition({ lat, lng });
                emit({
                    latitude: lat,
                    longitude: lng,
                    address_text: formatted,
                    formatted_address: formatted,
                    place_id: place.place_id ?? null,
                });
            });

            autocompleteRef.current = autocomplete;
        }

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
            if (autocompleteRef.current) {
                googleApi.maps.event.clearInstanceListeners(autocompleteRef.current);
            }

            if (markerRef.current) {
                googleApi.maps.event.clearInstanceListeners(markerRef.current);
                markerRef.current.setMap(null);
            }

            mapRef.current = null;
            markerRef.current = null;
            autocompleteRef.current = null;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [ready, googleApi, disabled]);

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
                <Input
                    ref={searchInputRef}
                    value={search}
                    disabled={disabled || !ready}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Calle, colonia, ciudad"
                    autoComplete="off"
                />
            </FormField>

            <div
                ref={mapNodeRef}
                className={`${mapHeightClassName} w-full overflow-hidden rounded-xl border border-border bg-muted`}
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
