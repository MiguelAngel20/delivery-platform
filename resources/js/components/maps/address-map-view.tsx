import {
    forwardRef,
    useEffect,
    useImperativeHandle,
    useRef,
    useState,
} from 'react';
import { MapCenterPin } from '@/components/maps/map-center-pin';
import { GOOGLE_MAPS_MAP_ID } from '@/lib/maps/advanced-marker';
import { cn } from '@/lib/utils';

export type AddressMapHandle = {
    panTo: (lat: number, lng: number, zoom?: number) => void;
    recenter: (lat: number, lng: number, zoom?: number) => void;
    triggerResize: () => void;
};

type AddressMapViewProps = {
    googleApi: typeof google;
    initialCenter: { lat: number; lng: number };
    initialZoom?: number;
    disabled?: boolean;
    className?: string;
    /** Disable pan/zoom (e.g. while address suggestions are open). */
    blockInteraction?: boolean;
    /** Optional coverage radius (meters) drawn as a circle around the pin. */
    radiusMeters?: number | null;
    onCenterSettled?: (lat: number, lng: number) => void;
};

export const AddressMapView = forwardRef<AddressMapHandle, AddressMapViewProps>(
    function AddressMapView(
        {
            googleApi,
            initialCenter,
            initialZoom = 14,
            disabled = false,
            className,
            blockInteraction = false,
            radiusMeters = null,
            onCenterSettled,
        },
        ref,
    ) {
        const mapNodeRef = useRef<HTMLDivElement | null>(null);
        const mapRef = useRef<google.maps.Map | null>(null);
        const circleRef = useRef<google.maps.Circle | null>(null);
        const skipIdleRef = useRef(false);
        const ignoreInitialIdleRef = useRef(true);
        const onCenterSettledRef = useRef(onCenterSettled);
        const [mapReady, setMapReady] = useState(false);

        onCenterSettledRef.current = onCenterSettled;

        const syncCircleCenter = (lat: number, lng: number) => {
            circleRef.current?.setCenter({ lat, lng });
        };

        useImperativeHandle(ref, () => ({
            panTo(lat: number, lng: number, zoom?: number) {
                const map = mapRef.current;

                if (!map) {
                    return;
                }

                skipIdleRef.current = true;
                map.panTo({ lat, lng });

                if (zoom !== undefined) {
                    map.setZoom(zoom);
                }

                syncCircleCenter(lat, lng);
            },
            recenter(lat: number, lng: number, zoom?: number) {
                const map = mapRef.current;

                if (!map) {
                    return;
                }

                skipIdleRef.current = true;
                map.setCenter({ lat, lng });

                if (zoom !== undefined) {
                    map.setZoom(zoom);
                }

                syncCircleCenter(lat, lng);
            },
            triggerResize() {
                const map = mapRef.current;

                if (!map) {
                    return;
                }

                googleApi.maps.event.trigger(map, 'resize');
            },
        }));

        useEffect(() => {
            if (!mapNodeRef.current || mapRef.current) {
                return;
            }

            const map = new googleApi.maps.Map(mapNodeRef.current, {
                center: initialCenter,
                zoom: initialZoom,
                mapId: GOOGLE_MAPS_MAP_ID,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false,
                zoomControl: !disabled,
                gestureHandling: disabled ? 'none' : 'greedy',
            });

            const idleListener = map.addListener('idle', () => {
                if (ignoreInitialIdleRef.current) {
                    ignoreInitialIdleRef.current = false;

                    return;
                }

                if (skipIdleRef.current) {
                    skipIdleRef.current = false;

                    return;
                }

                const center = map.getCenter();

                if (!center) {
                    return;
                }

                onCenterSettledRef.current?.(center.lat(), center.lng());
                syncCircleCenter(center.lat(), center.lng());
            });

            mapRef.current = map;
            setMapReady(true);

            const resizeTimer = window.setTimeout(() => {
                googleApi.maps.event.trigger(map, 'resize');
                map.setCenter(initialCenter);
            }, 200);

            return () => {
                window.clearTimeout(resizeTimer);
                googleApi.maps.event.removeListener(idleListener);
                circleRef.current?.setMap(null);
                circleRef.current = null;
                mapRef.current = null;
                setMapReady(false);
            };
            // Map is created once per mount.
            // eslint-disable-next-line react-hooks/exhaustive-deps
        }, [googleApi]);

        useEffect(() => {
            const map = mapRef.current;

            if (!map) {
                return;
            }

            map.setOptions({
                zoomControl: !disabled && !blockInteraction,
                gestureHandling:
                    disabled || blockInteraction ? 'none' : 'greedy',
            });
        }, [blockInteraction, disabled]);

        useEffect(() => {
            const map = mapRef.current;

            if (!map || !mapReady) {
                return;
            }

            const radius =
                typeof radiusMeters === 'number' && radiusMeters > 0
                    ? radiusMeters
                    : null;

            if (radius === null) {
                circleRef.current?.setMap(null);
                circleRef.current = null;

                return;
            }

            const center = map.getCenter();
            const lat = center?.lat() ?? initialCenter.lat;
            const lng = center?.lng() ?? initialCenter.lng;

            if (!circleRef.current) {
                circleRef.current = new googleApi.maps.Circle({
                    map,
                    center: { lat, lng },
                    radius,
                    strokeColor: '#ea580c',
                    strokeOpacity: 0.9,
                    strokeWeight: 2,
                    fillColor: '#f97316',
                    fillOpacity: 0.18,
                    clickable: false,
                });
            } else {
                circleRef.current.setMap(map);
                circleRef.current.setCenter({ lat, lng });
                circleRef.current.setRadius(radius);
            }

            const bounds = circleRef.current.getBounds();

            if (bounds) {
                skipIdleRef.current = true;
                map.fitBounds(bounds, 48);
            }
            // Only re-fit when the radius (or map readiness) changes — not on every pan.
            // eslint-disable-next-line react-hooks/exhaustive-deps
        }, [googleApi, mapReady, radiusMeters]);

        useEffect(() => {
            const map = mapRef.current;

            if (!map) {
                return;
            }

            const center = map.getCenter();
            const needsCenter =
                center === undefined
                || Math.abs(center.lat() - initialCenter.lat) > 1e-6
                || Math.abs(center.lng() - initialCenter.lng) > 1e-6;

            // Never re-apply initialZoom here: trackpad/pinch zoom must stick after idle.
            // Programmatic zoom goes through panTo/recenter instead.
            if (!needsCenter) {
                return;
            }

            skipIdleRef.current = true;
            map.setCenter(initialCenter);
            syncCircleCenter(initialCenter.lat, initialCenter.lng);
        }, [initialCenter.lat, initialCenter.lng]);

        return (
            <div className={cn('relative', className)}>
                <div
                    ref={mapNodeRef}
                    className={cn(
                        'size-full overflow-hidden rounded-xl border border-border bg-muted',
                        blockInteraction && 'pointer-events-none',
                    )}
                />
                <div
                    className="pointer-events-none absolute inset-0"
                    aria-hidden
                >
                    <div className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-full">
                        <MapCenterPin />
                    </div>
                </div>
            </div>
        );
    },
);
