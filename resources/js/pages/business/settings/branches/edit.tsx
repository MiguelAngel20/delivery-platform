import { Head } from '@inertiajs/react';
import {
    BusinessBranchForm,
    type BusinessBranchDetail,
} from '@/apps/business/components/business-branch-form';
import type {
    BusinessOpeningHour,
    EnumOption,
} from '@/apps/admin/businesses/types';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { BackButton } from '@/components/navigation/back-button';
import { Button } from '@/components/ui/button';
import business from '@/routes/business';
import { index, update } from '@/routes/business/settings/branches';

type Props = {
    branch: BusinessBranchDetail;
    options: {
        weekdays: EnumOption[];
        default_opening_hours: BusinessOpeningHour[];
    };
};

export default function BusinessSettingsBranchesEdit({
    branch,
    options,
}: Props) {
    return (
        <>
            <Head title={`Editar sucursal · ${branch.name}`} />
            <PageContainer>
                <PageHeader
                    title="Editar sucursal"
                    description={branch.name}
                    actions={
                        <BackButton href={index.url()} />
                    }
                />
                <ContentCard>
                    <BusinessBranchForm
                        branch={branch}
                        options={options}
                        action={update(branch.id)}
                        submitLabel="Guardar cambios"
                    />
                </ContentCard>
            </PageContainer>
        </>
    );
}

BusinessSettingsBranchesEdit.layout = {
    title: 'Editar sucursal',
    breadcrumbs: [
        {
            title: 'Configuración',
            href: business.settings.index(),
        },
        {
            title: 'Sucursales',
            href: business.settings.branches.index(),
        },
        {
            title: 'Editar',
            href: '#',
        },
    ],
};
