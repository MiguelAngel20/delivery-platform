import { Head, Link } from '@inertiajs/react';
import type { EmployeeFormOptions } from '@/apps/business/components/employee-form';
import { StatusBadge } from '@/components/data-display/status-badge';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import admin from '@/routes/admin';
import { show as businessShow } from '@/routes/admin/businesses';
import {
    create,
    edit,
} from '@/routes/admin/businesses/users';

type MembershipRow = {
    id: number;
    role: string;
    role_label: string;
    status: string;
    status_label: string;
    user: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
    };
    branches: Array<{ id: number; name: string }>;
};

type Props = {
    business: { id: number; name: string };
    users: MembershipRow[];
    options: EmployeeFormOptions;
};

export default function AdminBusinessUsersIndex({ business, users }: Props) {
    return (
        <>
            <Head title={`Usuarios · ${business.name}`} />
            <PageContainer>
                <PageHeader
                    title="Usuarios"
                    description={business.name}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={businessShow.url(business.id)}>
                                    Volver a empresa
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href={create.url(business.id)}>
                                    + Agregar usuario
                                </Link>
                            </Button>
                        </>
                    }
                />
                <ContentCard>
                    {users.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Esta empresa no tiene usuarios asociados.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border border-[#E2E8F0]">
                            <table className="min-w-full text-sm">
                                <thead className="bg-[#F8FAFC] text-left text-[#64748B]">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">
                                            Nombre
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Correo
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Teléfono
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Rol
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Sucursales
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Estado
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.map((membership) => (
                                        <tr
                                            key={membership.id}
                                            className="border-t border-[#E2E8F0]"
                                        >
                                            <td className="px-4 py-3 font-medium text-navy">
                                                {membership.user.name}
                                            </td>
                                            <td className="px-4 py-3">
                                                {membership.user.email}
                                            </td>
                                            <td className="px-4 py-3">
                                                {membership.user.phone ?? '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {membership.role_label}
                                            </td>
                                            <td className="px-4 py-3 text-[#475569]">
                                                {membership.role ===
                                                'business_admin'
                                                    ? 'Todas'
                                                    : membership.branches
                                                          .length > 0
                                                      ? membership.branches
                                                            .map((b) => b.name)
                                                            .join(', ')
                                                      : '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    tone={
                                                        membership.status ===
                                                        'active'
                                                            ? 'success'
                                                            : 'neutral'
                                                    }
                                                >
                                                    {membership.status_label}
                                                </StatusBadge>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={edit.url({
                                                            business:
                                                                business.id,
                                                            businessUser:
                                                                membership.id,
                                                        })}
                                                    >
                                                        Editar
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </ContentCard>
            </PageContainer>
        </>
    );
}

AdminBusinessUsersIndex.layout = {
    title: 'Usuarios de empresa',
    breadcrumbs: [
        {
            title: 'Empresas',
            href: admin.businesses.index(),
        },
        {
            title: 'Usuarios',
            href: '#',
        },
    ],
};
