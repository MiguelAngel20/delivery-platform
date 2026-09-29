import { Head } from '@inertiajs/react';
import type {
    AdminSectionOption,
    PermissionFlags,
} from '@/apps/admin/settings/admin-permission-fields';
import { ContentCard, PageContainer, PageHeader } from '@/components/layout/page';
import { BackButton } from '@/components/navigation/back-button';
import admin from '@/routes/admin';
import { index, store } from '@/routes/admin/settings/admins';
import { AdminUserForm } from './form';

type Props = {
    sections: AdminSectionOption[];
    permissions: Record<string, PermissionFlags>;
};

export default function CreateAdminUser({ sections, permissions }: Props) {
    return (
        <>
            <Head title="Agregar administrador" />
            <PageContainer>
                <PageHeader
                    title="Agregar administrador"
                    actions={<BackButton href={index.url()} />}
                />
                <ContentCard>
                    <AdminUserForm
                        sections={sections}
                        permissions={permissions}
                        action={store()}
                        submitLabel="Crear administrador"
                    />
                </ContentCard>
            </PageContainer>
        </>
    );
}

CreateAdminUser.layout = {
    title: 'Agregar administrador',
    breadcrumbs: [
        { title: 'Configuración', href: admin.settings.index() },
        { title: 'Administradores', href: index.url() },
        { title: 'Agregar', href: '#' },
    ],
};
