import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    EmployeeForm
    
} from '@/apps/business/components/employee-form';
import type {EmployeeFormOptions} from '@/apps/business/components/employee-form';
import { ConfirmDialog } from '@/components/dialogs/modal';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import admin from '@/routes/admin';
import {
    activate,
    deactivate,
    index as usersIndex,
    update,
} from '@/routes/admin/businesses/users';

type MembershipDetail = {
    id: number;
    role: string;
    status: string;
    user: {
        first_name: string;
        last_name: string;
        email: string;
        phone: string;
    };
    branch_ids: number[];
};

type Props = {
    business: { id: number; name: string };
    userMembership: MembershipDetail;
    options: EmployeeFormOptions;
};

export default function AdminBusinessUsersEdit({
    business,
    userMembership,
    options,
}: Props) {
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const isActive = userMembership.status === 'active';

    return (
        <>
            <Head title={`Editar usuario · ${business.name}`} />
            <PageContainer>
                <PageHeader
                    title="Editar usuario"
                    description={business.name}
                    actions={
                        <Button
                            type="button"
                            variant={isActive ? 'danger' : 'primary'}
                            onClick={() => setConfirmOpen(true)}
                        >
                            {isActive
                                ? 'Desactivar membresía'
                                : 'Reactivar membresía'}
                        </Button>
                    }
                />
                <ContentCard>
                    <EmployeeForm
                        options={options}
                        employee={{
                            id: userMembership.id,
                            first_name: userMembership.user.first_name,
                            last_name: userMembership.user.last_name,
                            email: userMembership.user.email,
                            phone: userMembership.user.phone,
                            role: userMembership.role,
                            status: userMembership.status,
                            branch_ids: userMembership.branch_ids,
                        }}
                        action={update({
                            business: business.id,
                            businessUser: userMembership.id,
                        })}
                        submitLabel="Guardar cambios"
                        cancelSlot={
                            <Button variant="outline" asChild>
                                <Link href={usersIndex.url(business.id)}>
                                    Volver
                                </Link>
                            </Button>
                        }
                    />
                </ContentCard>
            </PageContainer>

            <ConfirmDialog
                open={confirmOpen}
                onOpenChange={setConfirmOpen}
                title={
                    isActive
                        ? 'Desactivar membresía'
                        : 'Reactivar membresía'
                }
                description={
                    isActive
                        ? 'El usuario dejará de operar en esta empresa. No se elimina el usuario del sistema.'
                        : 'El usuario recuperará acceso según su rol y sucursales.'
                }
                confirmLabel={isActive ? 'Desactivar' : 'Reactivar'}
                variant={isActive ? 'danger' : 'primary'}
                loading={loading}
                onConfirm={() => {
                    setLoading(true);
                    router.post(
                        isActive
                            ? deactivate.url({
                                  business: business.id,
                                  businessUser: userMembership.id,
                              })
                            : activate.url({
                                  business: business.id,
                                  businessUser: userMembership.id,
                              }),
                        {},
                        {
                            preserveScroll: true,
                            onFinish: () => {
                                setLoading(false);
                                setConfirmOpen(false);
                            },
                        },
                    );
                }}
            />
        </>
    );
}

AdminBusinessUsersEdit.layout = {
    title: 'Editar usuario',
    breadcrumbs: [
        {
            title: 'Empresas',
            href: admin.businesses.index(),
        },
        {
            title: 'Editar usuario',
            href: '#',
        },
    ],
};
