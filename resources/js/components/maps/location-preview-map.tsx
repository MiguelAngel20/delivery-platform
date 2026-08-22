import { MapPin } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { useGoogleMaps } from '@/components/maps/google-maps-provider';
import {
    GOOGLE_MAPS_MAP_ID,
    createAdvancedMarker,
} from '@/lib/maps/advanced-marker';
import { cn } from '@/lib/utils';

type LocationPreviewMapProps = {
    latitude: number;
    longitude: number;
    title?: string;
    className?: string;
};

export function LocationPreviewMap({
    latitude,
    longitude,
    title,
    className,
}: LocationPreviewMapProps) {
    const { ready, error, google: googleApi } = useGoogleMaps();
    const mapNodeRef = useRef<HTMLDivElement | null>(null);
    const mapRef = useRef<google.maps.Map | null>(null);
    const markerRef = useRef<google.maps.marker.AdvancedMarkerElement | null>(
        null,
    );

    useEffect(() => {
        if (!ready || !googleApi || !mapNodeRef.current) {
            return;
        }

        const center = { lat: latitude, lng: longitude };

        if (!mapRef.current) {
            mapRef.current = new googleApi.maps.Map(mapNodeRef.current, {
                center,
                zoom: 16,
                mapId: GOOGLE_MAPS_MAP_ID,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false,
                zoomControl: true,
                gestureHandling: 'cooperative',
                clickableIcons: false,
            });

            markerRef.current = createAdvancedMarker(googleApi, {
                map: mapRef.current,
                position: center,
                title,
            });
        } else {
            mapRef.current.setCenter(center);
            if (markerRef.current) {
                markerRef.current.position = center;
                markerRef.current.title = title ?? '';
            }
        }

        const map = mapRef.current;
        const fitMap = window.setTimeout(() => {
            googleApi.maps.event.trigger(map, 'resize');
            map.setCenter(center);
        }, 250);

        return () => window.clearTimeout(fitMap);
    }, [googleApi, latitude, longitude, ready, title]);

    return (
        <div className={cn('relative h-full w-full bg-secondary', className)}>
            <div ref={mapNodeRef} className="h-full w-full" />
            {!ready || error ? (
                <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-secondary text-muted-foreground">
                    <MapPin className="size-8 text-primary" />
                    <p className="text-sm font-medium text-navy">
                        {error ?? 'Cargando mapa…'}
                    </p>
                </div>
            ) : null}
        </div>
    );
}
