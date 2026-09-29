import { Head, Link, router } from '@inertiajs/react';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import admin from '@/routes/admin';
import { activate, create, deactivate, edit, index } from '@/routes/admin/settings/admins';

type AdminRow = {
    id: number;
    name: string;
    email: string | null;
    phone: string;
    status: string;
    status_label: string;
    is_owner: boolean;
    sections: string[];
};

type Props = {
    admins: AdminRow[];
};

export default function AdminUsersIndex({ admins }: Props) {
    return (
        <>
            <Head title="Administradores" />
            <PageContainer>
                <PageHeader
                    title="Administradores"
                    description="Cuentas con acceso al panel. Solo tú puedes crearlas y elegir sus permisos."
                    actions={
                        <Button asChild>
                            <Link href={create.url()}>Agregar</Link>
                        </Button>
                    }
                />
                <div className="overflow-hidden rounded-xl border border-border bg-white">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Cuenta</th>
                                <th className="px-4 py-3 font-medium">Acceso</th>
                                <th className="px-4 py-3 font-medium">Estado</th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {admins.map((row) => (
                                <tr
                                    key={row.id}
                                    className="border-b border-border last:border-0"
                                >
                                    <td className="px-4 py-3">
                                        <p className="font-medium text-navy">
                                            {row.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {row.email}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {row.is_owner
                                            ? 'Acceso completo'
                                            : row.sections.join(', ') ||
                                              'Sin secciones'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {row.status_label}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {row.is_owner ? null : (
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link href={edit.url(row.id)}>
                                                        Permisos
                                                    </Link>
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        router.post(
                                                            row.status ===
                                                                'active'
                                                                ? deactivate.url(
                                                                      row.id,
                                                                  )
                                                                : activate.url(
                                                                      row.id,
                                                                  ),
                                                        )
                                                    }
                                                >
                                                    {row.status === 'active'
                                                        ? 'Desactivar'
                                                        : 'Reactivar'}
                                                </Button>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </PageContainer>
        </>
    );
}

AdminUsersIndex.layout = {
    title: 'Administradores',
    breadcrumbs: [
        { title: 'Configuración', href: admin.settings.index() },
        { title: 'Administradores', href: index.url() },
    ],
};
