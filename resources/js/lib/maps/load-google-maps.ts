type LoadOptions = {
    apiKey: string;
    libraries?: string[];
};

const SCRIPT_ID = 'ride-google-maps-sdk';

export async function loadGoogleMaps({
    apiKey,
    libraries = ['places'],
}: LoadOptions): Promise<typeof google> {
    if (typeof window === 'undefined') {
        throw new Error('Google Maps solo está disponible en el navegador.');
    }

    if (!apiKey) {
        throw new Error('Falta GOOGLE_MAPS_API_KEY.');
    }

    if (window.google?.maps) {
        return window.google;
    }

    if (window.__rideGoogleMapsPromise) {
        return window.__rideGoogleMapsPromise;
    }

    window.__rideGoogleMapsPromise = new Promise((resolve, reject) => {
        const existing = document.getElementById(SCRIPT_ID) as HTMLScriptElement | null;

        if (existing) {
            existing.addEventListener('load', () => {
                if (window.google?.maps) {
                    resolve(window.google);
                } else {
                    reject(new Error('Google Maps no inicializó.'));
                }
            });
            existing.addEventListener('error', () =>
                reject(new Error('No se pudo cargar Google Maps.')),
            );

            return;
        }

        const script = document.createElement('script');
        script.id = SCRIPT_ID;
        script.async = true;
        script.defer = true;
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&libraries=${libraries.join(',')}`;
        script.onload = () => {
            if (window.google?.maps) {
                resolve(window.google);
            } else {
                reject(new Error('Google Maps no inicializó.'));
            }
        };
        script.onerror = () => reject(new Error('No se pudo cargar Google Maps.'));
        document.head.appendChild(script);
    });

    return window.__rideGoogleMapsPromise;
}
