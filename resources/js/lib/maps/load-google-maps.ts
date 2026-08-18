type LoadOptions = {
    apiKey: string;
};

const BOOTSTRAP_ID = 'ride-google-maps-bootstrap';

declare global {
    interface Window {
        google?: typeof google;
        __rideGoogleMapsPromise?: Promise<typeof google>;
    }
}

function purgeLegacyGoogleMapsLoader(): void {
    if (window.google?.maps?.importLibrary) {
        return;
    }

    document
        .querySelectorAll('script[src*="maps.googleapis.com/maps/api/js"]')
        .forEach((script) => script.remove());

    document.getElementById(BOOTSTRAP_ID)?.remove();

    if (window.google) {
        delete window.google;
    }

    delete window.__rideGoogleMapsPromise;
}

function installBootstrapLoader(apiKey: string): void {
    if (window.google?.maps?.importLibrary) {
        return;
    }

    if (document.getElementById(BOOTSTRAP_ID)) {
        return;
    }

    const bootstrap = document.createElement('script');
    bootstrap.id = BOOTSTRAP_ID;
    bootstrap.textContent = `(g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]+"");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=\`https://maps.googleapis.com/maps/api/js?\`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?console.warn(p+" only loads once. Ignoring...",g):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})(${JSON.stringify(
        {
            key: apiKey,
            v: 'weekly',
        },
    )});`;
    document.head.appendChild(bootstrap);
}

async function waitForImportLibrary(
    attempts = 100,
    delayMs = 50,
): Promise<void> {
    for (let attempt = 0; attempt < attempts; attempt += 1) {
        if (window.google?.maps?.importLibrary) {
            return;
        }

        await new Promise((resolve) => window.setTimeout(resolve, delayMs));
    }

    throw new Error(
        'Google Maps no inicializó correctamente. Recarga la página e intenta de nuevo.',
    );
}

export async function loadGoogleMaps({
    apiKey,
}: LoadOptions): Promise<typeof google> {
    if (typeof window === 'undefined') {
        throw new Error('Google Maps solo está disponible en el navegador.');
    }

    if (!apiKey) {
        throw new Error('Falta GOOGLE_MAPS_API_KEY.');
    }

    if (window.__rideGoogleMapsPromise) {
        return window.__rideGoogleMapsPromise;
    }

    window.__rideGoogleMapsPromise = (async () => {
        purgeLegacyGoogleMapsLoader();
        installBootstrapLoader(apiKey);
        await waitForImportLibrary();

        await Promise.all([
            window.google!.maps.importLibrary('maps'),
            window.google!.maps.importLibrary('places'),
        ]);

        return window.google!;
    })().catch((error) => {
        delete window.__rideGoogleMapsPromise;

        throw error instanceof Error
            ? error
            : new Error('No se pudo cargar Google Maps.');
    });

    return window.__rideGoogleMapsPromise;
}
