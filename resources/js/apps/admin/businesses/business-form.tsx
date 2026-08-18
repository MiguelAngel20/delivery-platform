import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type {
    BusinessDetail,
    BusinessFormOptions,
} from '@/apps/admin/businesses/types';
import { FormField } from '@/components/forms/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

type BusinessFormProps = {
    options: BusinessFormOptions;
    business?: BusinessDetail;
    action:
        | {
              url: string;
              method: 'post' | 'put' | 'patch' | 'delete' | 'get';
          }
        | {
              action: string;
              method: 'get' | 'post';
          };
    submitLabel: string;
    cancelSlot?: ReactNode;
};

export function BusinessForm({
    options,
    business,
    action,
    submitLabel,
    cancelSlot,
}: BusinessFormProps) {
    const formProps =
        'action' in action
            ? { action: action.action, method: action.method }
            : { action: action.url, method: action.method };

    return (
        <Form
            {...formProps}
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
                                defaultValue={business?.name ?? ''}
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
                                defaultValue={business?.description ?? ''}
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
                                    business?.business_type ??
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

                        <FormField
                            label="Modalidad operativa"
                            htmlFor="operation_mode"
                            required
                            error={errors.operation_mode}
                        >
                            <select
                                id="operation_mode"
                                name="operation_mode"
                                required
                                defaultValue={
                                    business?.operation_mode ??
                                    options.operation_modes[0]?.value
                                }
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                {options.operation_modes.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </FormField>

                        <FormField
                            label="Modalidad de entrega"
                            htmlFor="delivery_mode"
                            required
                            error={errors.delivery_mode}
                        >
                            <select
                                id="delivery_mode"
                                name="delivery_mode"
                                required
                                defaultValue={
                                    business?.delivery_mode ??
                                    options.delivery_modes[0]?.value
                                }
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                {options.delivery_modes.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </FormField>

                        <FormField
                            label="Estado"
                            htmlFor="status"
                            required
                            error={errors.status}
                        >
                            <select
                                id="status"
                                name="status"
                                required
                                defaultValue={
                                    business?.status ?? 'pending_approval'
                                }
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                {options.statuses.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </FormField>

                        <FormField
                            label="Teléfono"
                            htmlFor="phone"
                            error={errors.phone}
                        >
                            <Input
                                id="phone"
                                name="phone"
                                defaultValue={business?.phone ?? ''}
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
                                defaultValue={business?.email ?? ''}
                            />
                        </FormField>

                        <FormField
                            label="Logo"
                            htmlFor="logo"
                            error={errors.logo}
                            hint={
                                business?.logo_url
                                    ? 'Sube una imagen para reemplazar el logo actual.'
                                    : 'JPG, PNG o WebP. Máx. 2 MB.'
                            }
                            className="md:col-span-2"
                        >
                            {business?.logo_url ? (
                                <img
                                    src={business.logo_url}
                                    alt={`Logo de ${business.name}`}
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
                                business?.banner_url
                                    ? 'Imagen horizontal para el inicio. Sube otra para reemplazarla.'
                                    : 'Imagen horizontal (recomendado ~1200×400). Solo afiliadas aparecen en el carrusel. JPG, PNG o WebP. Máx. 4 MB.'
                            }
                            className="md:col-span-2"
                        >
                            {business?.banner_url ? (
                                <img
                                    src={business.banner_url}
                                    alt={`Banner de ${business.name}`}
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
