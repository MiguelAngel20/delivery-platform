import { PortalInstallAppBanner } from '@/components/pwa/portal-install-app-banner';

export function DriverInstallAppBanner() {
    return (
        <PortalInstallAppBanner
            scope="driver"
            appName="ChisDrive Repartidor"
            body="Accede a tus pedidos desde la pantalla de inicio, como una app."
            ariaLabel="Instalar aplicación de repartidor"
            mobileOnly
            className="bottom-20"
        />
    );
}
