import {
    forwardRef,
    useEffect,
    useImperativeHandle,
    useRef,
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
    onCenterSettled: (lat: number, lng: number) => void;
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
            onCenterSettled,
        },
        ref,
    ) {
        const mapNodeRef = useRef<HTMLDivElement | null>(null);
        const mapRef = useRef<google.maps.Map | null>(null);
        const skipIdleRef = useRef(false);
        const ignoreInitialIdleRef = useRef(true);
        const onCenterSettledRef = useRef(onCenterSettled);

        onCenterSettledRef.current = onCenterSettled;

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

                onCenterSettledRef.current(center.lat(), center.lng());
            });

            mapRef.current = map;

            const resizeTimer = window.setTimeout(() => {
                googleApi.maps.event.trigger(map, 'resize');
                map.setCenter(initialCenter);
            }, 200);

            return () => {
                window.clearTimeout(resizeTimer);
                googleApi.maps.event.removeListener(idleListener);
                mapRef.current = null;
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

            if (!map) {
                return;
            }

            const center = map.getCenter();
            const needsCenter =
                center === undefined
                || Math.abs(center.lat() - initialCenter.lat) > 1e-6
                || Math.abs(center.lng() - initialCenter.lng) > 1e-6;
            const needsZoom = map.getZoom() !== initialZoom;

            if (!needsCenter && !needsZoom) {
                return;
            }

            skipIdleRef.current = true;

            if (needsCenter) {
                map.setCenter(initialCenter);
            }

            if (needsZoom) {
                map.setZoom(initialZoom);
            }
        }, [initialCenter.lat, initialCenter.lng, initialZoom]);

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
