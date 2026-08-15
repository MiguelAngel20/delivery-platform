import type { ReactNode } from 'react';
import { GoogleMapsProvider } from '@/components/maps/google-maps-provider';

/**
 * Persistent layout so Google Maps can read shared Inertia props via usePage.
 * Must wrap page content (not withApp), because withApp sits outside Inertia context.
 */
export default function MapsLayout({ children }: { children: ReactNode }) {
    return <GoogleMapsProvider>{children}</GoogleMapsProvider>;
}
