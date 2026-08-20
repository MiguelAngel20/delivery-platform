import { Head } from '@inertiajs/react';
import {
    BusinessProfileForm,
    type BusinessProfileDetail,
    type BusinessProfileFormOptions,
} from '@/apps/business/components/business-profile-form';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { BackButton } from '@/components/navigation/back-button';
import business from '@/routes/business';
import { update } from '@/routes/business/settings/business';

type Props = {
    business: BusinessProfileDetail;
    options: BusinessProfileFormOptions;
};

export default function BusinessSettingsBusiness({
    business: businessProfile,
    options,
}: Props) {
    return (
        <>
            <Head title="Información del negocio" />
            <PageContainer>
                <PageHeader
                    title="Información del negocio"
                    description="Datos de contacto e imagen de tu establecimiento."
                    actions={
                        <BackButton href={business.settings.index.url()} />
                    }
                />
                <ContentCard>
                    <BusinessProfileForm
                        business={businessProfile}
                        options={options}
                        action={update()}
                        submitLabel="Guardar cambios"
                    />
                </ContentCard>
            </PageContainer>
        </>
    );
}

BusinessSettingsBusiness.layout = {
    title: 'Información del negocio',
    breadcrumbs: [
        {
            title: 'Configuración',
            href: business.settings.index(),
        },
        {
            title: 'Información del negocio',
            href: business.settings.business.edit(),
        },
    ],
};
