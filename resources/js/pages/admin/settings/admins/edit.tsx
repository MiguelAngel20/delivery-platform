import { Head } from '@inertiajs/react';
import type {
    AdminSectionOption,
    PermissionFlags,
} from '@/apps/admin/settings/admin-permission-fields';
import { ContentCard, PageContainer, PageHeader } from '@/components/layout/page';
import { BackButton } from '@/components/navigation/back-button';
import admin from '@/routes/admin';
import { index, update } from '@/routes/admin/settings/admins';
import { AdminUserForm } from './form';

type Props = {
    adminUser: {
        id: number;
        first_name: string;
        last_name: string;
        email: string | null;
        phone: string;
    };
    sections: AdminSectionOption[];
    permissions: Record<string, PermissionFlags>;
};

export default function EditAdminUser({
    adminUser,
    sections,
    permissions,
}: Props) {
    return (
        <>
            <Head title="Permisos del administrador" />
            <PageContainer>
                <PageHeader
                    title="Permisos del administrador"
                    actions={<BackButton href={index.url()} />}
                />
                <ContentCard>
                    <AdminUserForm
                        adminUser={adminUser}
                        sections={sections}
                        permissions={permissions}
                        action={update(adminUser.id)}
                        submitLabel="Guardar permisos"
                    />
                </ContentCard>
            </PageContainer>
        </>
    );
}

EditAdminUser.layout = {
    title: 'Permisos del administrador',
    breadcrumbs: [
        { title: 'Configuración', href: admin.settings.index() },
        { title: 'Administradores', href: index.url() },
        { title: 'Permisos', href: '#' },
    ],
};
