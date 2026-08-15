import {
    createContext,
    useContext,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';
import { usePage } from '@inertiajs/react';
import { loadGoogleMaps } from '@/lib/maps/load-google-maps';
import type { MapsSharedConfig } from '@/lib/maps/types';

type GoogleMapsContextValue = {
    ready: boolean;
    error: string | null;
    apiKey: string;
    defaultCenter: MapsSharedConfig['default_center'];
    google: typeof google | null;
};

const GoogleMapsContext = createContext<GoogleMapsContextValue | null>(null);

export function GoogleMapsProvider({ children }: { children: ReactNode }) {
    const page = usePage<{ maps?: MapsSharedConfig }>();
    const maps = page.props.maps;
    const apiKey = maps?.browser_api_key ?? '';
    const defaultCenter = maps?.default_center ?? {
        latitude: 16.2514,
        longitude: -92.1342,
        zoom: 14,
    };

    const [ready, setReady] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [googleApi, setGoogleApi] = useState<typeof google | null>(null);

    useEffect(() => {
        let cancelled = false;

        if (typeof window !== 'undefined' && window.google?.maps) {
            setGoogleApi(window.google);
            setReady(true);
        }

        if (!apiKey) {
            setError('Maps no configurado.');
            setReady(false);

            return;
        }

        loadGoogleMaps({ apiKey })
            .then((api) => {
                if (cancelled) {
                    return;
                }

                setGoogleApi(api);
                setReady(true);
                setError(null);
            })
            .catch((err: Error) => {
                if (cancelled) {
                    return;
                }

                setError(err.message);
                setReady(false);
            });

        return () => {
            cancelled = true;
        };
    }, [apiKey]);

    const value = useMemo(
        () => ({
            ready,
            error,
            apiKey,
            defaultCenter,
            google: googleApi,
        }),
        [ready, error, apiKey, defaultCenter, googleApi],
    );

    return (
        <GoogleMapsContext.Provider value={value}>
            {children}
        </GoogleMapsContext.Provider>
    );
}

export function useGoogleMaps(): GoogleMapsContextValue {
    const context = useContext(GoogleMapsContext);

    if (context === null) {
        throw new Error('useGoogleMaps debe usarse dentro de GoogleMapsProvider.');
    }

    return context;
}
