import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { FormField } from '@/components/forms/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

export type BusinessProfileFormOptions = {
    business_types: string[];
    operation_modes: Array<{ value: string; label: string }>;
};

export type BusinessProfileDetail = {
    name: string;
    description: string | null;
    business_type: string | null;
    operation_mode: string;
    operation_mode_label: string;
    delivery_mode_label: string;
    status_label: string;
    phone: string | null;
    email: string | null;
    logo_url: string | null;
    banner_url: string | null;
};

type BusinessProfileFormProps = {
    options: BusinessProfileFormOptions;
    business: BusinessProfileDetail;
    action: {
        url: string;
        method: 'post' | 'put' | 'patch';
    };
    submitLabel: string;
    cancelSlot?: ReactNode;
};

export function BusinessProfileForm({
    options,
    business,
    action,
    submitLabel,
    cancelSlot,
}: BusinessProfileFormProps) {
    return (
        <Form
            action={action.url}
            method={action.method}
            encType="multipart/form-data"
            className="space-y-6"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-4 md:grid-cols-2">
                        <FormField
                            label="Nombre comercial"
                            htmlFor="name"
                            required
                            error={errors.name}
                            className="md:col-span-2"
                        >
                            <Input
                                id="name"
                                name="name"
                                required
                                defaultValue={business.name}
                            />
                        </FormField>

                        <FormField
                            label="Descripción"
                            htmlFor="description"
                            error={errors.description}
                            className="md:col-span-2"
                        >
                            <Textarea
                                id="description"
                                name="description"
                                rows={4}
                                defaultValue={business.description ?? ''}
                            />
                        </FormField>

                        <FormField
                            label="Tipo / giro"
                            htmlFor="business_type"
                            required
                            error={errors.business_type}
                        >
                            <select
                                id="business_type"
                                name="business_type"
                                required
                                defaultValue={
                                    business.business_type ??
                                    options.business_types[0]
                                }
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                {options.business_types.map((type) => (
                                    <option key={type} value={type}>
                                        {type}
                                    </option>
                                ))}
                            </select>
                        </FormField>

                        <FormField label="Modalidad operativa">
                            <p className="text-sm text-muted-foreground">
                                {business.operation_mode_label}
                            </p>
                        </FormField>

                        <FormField label="Modalidad de entrega">
                            <p className="text-sm text-muted-foreground">
                                {business.delivery_mode_label}
                            </p>
                        </FormField>

                        <FormField label="Estado de la cuenta">
                            <p className="text-sm text-muted-foreground">
                                {business.status_label}
                            </p>
                        </FormField>

                        <FormField
                            label="Teléfono"
                            htmlFor="phone"
                            error={errors.phone}
                        >
                            <Input
                                id="phone"
                                name="phone"
                                defaultValue={business.phone ?? ''}
                            />
                        </FormField>

                        <FormField
                            label="Correo"
                            htmlFor="email"
                            error={errors.email}
                        >
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                defaultValue={business.email ?? ''}
                            />
                        </FormField>

                        <FormField
                            label="Logo"
                            htmlFor="logo"
                            error={errors.logo}
                            hint={
                                business.logo_url
                                    ? 'Sube una imagen para reemplazar el logo actual.'
                                    : 'JPG, PNG o WebP. Máx. 2 MB.'
                            }
                            className="md:col-span-2"
                        >
                            {business.logo_url ? (
                                <img
                                    src={business.logo_url}
                                    alt="Logo del negocio"
                                    className="mb-3 h-16 w-16 rounded-md border object-cover"
                                />
                            ) : null}
                            <Input
                                id="logo"
                                name="logo"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                            />
                        </FormField>

                        <FormField
                            label="Banner del carrusel"
                            htmlFor="banner"
                            error={errors.banner}
                            hint={
                                business.banner_url
                                    ? 'Imagen horizontal para el inicio. Sube otra para reemplazarla.'
                                    : 'Imagen horizontal (recomendado ~1200×400). JPG, PNG o WebP. Máx. 4 MB.'
                            }
                            className="md:col-span-2"
                        >
                            {business.banner_url ? (
                                <img
                                    src={business.banner_url}
                                    alt="Banner del negocio"
                                    className="mb-3 h-28 w-full max-w-xl rounded-md border object-cover"
                                />
                            ) : null}
                            <Input
                                id="banner"
                                name="banner"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                            />
                        </FormField>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button type="submit" loading={processing}>
                            {submitLabel}
                        </Button>
                        {cancelSlot}
                    </div>
                </>
            )}
        </Form>
    );
}
