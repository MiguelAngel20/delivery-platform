import { useForm } from '@inertiajs/react';
import { AdminPermissionFields } from '@/apps/admin/settings/admin-permission-fields';
import type {
    AdminSectionOption,
    PermissionFlags,
} from '@/apps/admin/settings/admin-permission-fields';
import { FormField } from '@/components/forms/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
type FormAction = {
    url: string;
    method: 'post' | 'put' | 'patch';
};

type AdminFormUser = {
    id: number;
    first_name: string;
    last_name: string;
    email: string | null;
    phone: string;
};

type Props = {
    sections: AdminSectionOption[];
    permissions: Record<string, PermissionFlags>;
    adminUser?: AdminFormUser;
    action: FormAction;
    submitLabel: string;
};

export function AdminUserForm({
    sections,
    permissions,
    adminUser,
    action,
    submitLabel,
}: Props) {
    const form = useForm({
        first_name: adminUser?.first_name ?? '',
        last_name: adminUser?.last_name ?? '',
        email: adminUser?.email ?? '',
        phone: adminUser?.phone ?? '',
        password: '',
        password_confirmation: '',
        permissions,
    });

    return (
        <form
            className="space-y-6"
            onSubmit={(event) => {
                event.preventDefault();

                if (action.method === 'post') {
                    form.post(action.url);

                    return;
                }

                form.put(action.url);
            }}
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <FormField label="Nombre" htmlFor="first_name" required error={form.errors.first_name}>
                    <Input
                        id="first_name"
                        value={form.data.first_name}
                        onChange={(event) =>
                            form.setData('first_name', event.target.value)
                        }
                    />
                </FormField>
                <FormField label="Apellidos" htmlFor="last_name" required error={form.errors.last_name}>
                    <Input
                        id="last_name"
                        value={form.data.last_name}
                        onChange={(event) =>
                            form.setData('last_name', event.target.value)
                        }
                    />
                </FormField>
                <FormField label="Correo" htmlFor="email" required error={form.errors.email}>
                    <Input
                        id="email"
                        type="email"
                        value={form.data.email}
                        onChange={(event) =>
                            form.setData('email', event.target.value)
                        }
                    />
                </FormField>
                <FormField label="Teléfono" htmlFor="phone" required error={form.errors.phone}>
                    <Input
                        id="phone"
                        value={form.data.phone}
                        onChange={(event) =>
                            form.setData('phone', event.target.value)
                        }
                    />
                </FormField>
                <FormField
                    label="Contraseña"
                    htmlFor="password"
                    required={adminUser == null}
                    error={form.errors.password}
                >
                    <Input
                        id="password"
                        type="password"
                        autoComplete="new-password"
                        value={form.data.password}
                        onChange={(event) =>
                            form.setData('password', event.target.value)
                        }
                    />
                </FormField>
                <FormField
                    label="Confirmar contraseña"
                    htmlFor="password_confirmation"
                    required={adminUser == null}
                >
                    <Input
                        id="password_confirmation"
                        type="password"
                        autoComplete="new-password"
                        value={form.data.password_confirmation}
                        onChange={(event) =>
                            form.setData(
                                'password_confirmation',
                                event.target.value,
                            )
                        }
                    />
                </FormField>
            </div>
            {adminUser != null ? (
                <p className="text-sm text-muted-foreground">
                    Deja la contraseña vacía si no quieres cambiarla.
                </p>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Mínimo 8 caracteres, con mayúscula, número y un carácter
                    especial.
                </p>
            )}
            <AdminPermissionFields
                sections={sections}
                permissions={form.data.permissions}
                onChange={(next) => form.setData('permissions', next)}
            />
            <Button type="submit" disabled={form.processing}>
                {submitLabel}
            </Button>
        </form>
    );
}
