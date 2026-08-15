import { Head, Link } from '@inertiajs/react';
import { mockSettingsSections } from '@/apps/business/mocks';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import business from '@/routes/business';

export default function BusinessSettingsIndex() {
    return (
        <>
            <Head title="Configuración" />
            <PageContainer>
                <PageHeader
                    title="Configuración"
                    description="Preferencias del establecimiento"
                />

                <ContentCard
                    title="Notificaciones"
                    description="Push y preferencias operativas"
                    actions={
                        <Button asChild variant="outline" size="sm">
                            <Link href="/business/settings/notifications">
                                Configurar
                            </Link>
                        </Button>
                    }
                >
                    <p className="text-sm text-[#64748B]">
                        Activa avisos de nuevas comandas, cancelaciones e
                        incidencias.
                    </p>
                </ContentCard>

                <div className="mt-4 grid gap-4 md:grid-cols-2">
                    {mockSettingsSections.map((section) => (
                        <ContentCard
                            key={section.key}
                            title={section.title}
                            description={section.description}
                            actions={
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled
                                >
                                    Configurar
                                </Button>
                            }
                        >
                            <p className="text-sm text-[#64748B]">
                                Sección preparada para configuración futura.
                            </p>
                        </ContentCard>
                    ))}
                </div>
            </PageContainer>
        </>
    );
}

BusinessSettingsIndex.layout = {
    title: 'Configuración',
    breadcrumbs: [
        {
            title: 'Configuración',
            href: business.settings.index(),
        },
    ],
};
