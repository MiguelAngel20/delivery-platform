import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/forms/form-field';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import business from '@/routes/business';
import { store } from '@/routes/business/upgrade-requests';

type Props = {
    business: { id: number; name: string };
    requests: Array<{
        id: number;
        type: string;
        type_label: string;
        requested_quantity: number;
        status: string;
        status_label: string;
        notes: string | null;
        branch: { id: number; name: string } | null;
        created_at: string | null;
    }>;
    options: {
        types: Array<{ value: string; label: string }>;
        branches: Array<{ id: number; name: string }>;
    };
};

export default function BusinessUpgradeRequestsIndex({
    business,
    requests,
    options,
}: Props) {
    const [type, setType] = useState(
        options.types[0]?.value ?? 'additional_branch',
    );

    return (
        <>
            <Head title="Solicitudes" />
            <PageContainer>
                <PageHeader
                    title="Solicitudes"
                    description={`${business.name} · ampliaciones comerciales`}
                />

                <ContentCard title="Nueva solicitud">
                    <Form
                        {...store.form()}
                        className="grid gap-4 md:grid-cols-2"
                        options={{ preserveScroll: true }}
                    >
                        {({ processing, errors }) => (
                            <>
                                <FormField
                                    label="Tipo"
                                    htmlFor="type"
                                    required
                                    error={errors.type}
                                >
                                    <select
                                        id="type"
                                        name="type"
                                        value={type}
                                        onChange={(event) =>
                                            setType(event.target.value)
                                        }
                                        className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs"
                                        required
                                    >
                                        {options.types.map((option) => (
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
                                    label="Cantidad solicitada"
                                    htmlFor="requested_quantity"
                                    required
                                    error={errors.requested_quantity}
                                >
                                    <Input
                                        id="requested_quantity"
                                        name="requested_quantity"
                                        type="number"
                                        min={1}
                                        defaultValue={1}
                                        required
                                    />
                                </FormField>
                                {type === 'additional_employees' ? (
                                    <FormField
                                        label="Sucursal"
                                        htmlFor="branch_id"
                                        required
                                        error={errors.branch_id}
                                    >
                                        <select
                                            id="branch_id"
                                            name="branch_id"
                                            required
                                            className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs"
                                            defaultValue=""
                                        >
                                            <option value="" disabled>
                                                Selecciona sucursal
                                            </option>
                                            {options.branches.map((branch) => (
                                                <option
                                                    key={branch.id}
                                                    value={branch.id}
                                                >
                                                    {branch.name}
                                                </option>
                                            ))}
                                        </select>
                                    </FormField>
                                ) : null}
                                <FormField
                                    label="Notas"
                                    htmlFor="notes"
                                    error={errors.notes}
                                    className="md:col-span-2"
                                >
                                    <Textarea
                                        id="notes"
                                        name="notes"
                                        rows={3}
                                    />
                                </FormField>
                                <div className="md:col-span-2">
                                    <Button type="submit" loading={processing}>
                                        Enviar solicitud
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </ContentCard>

                <ContentCard title="Historial">
                    {requests.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Aún no hay solicitudes.
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {requests.map((item) => (
                                <div
                                    key={item.id}
                                    className="rounded-lg border border-[#E2E8F0] px-4 py-3"
                                >
                                    <p className="font-medium text-navy">
                                        {item.type_label} · +
                                        {item.requested_quantity}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {item.branch
                                            ? `${item.branch.name} · `
                                            : ''}
                                        {item.status_label}
                                    </p>
                                </div>
                            ))}
                        </div>
                    )}
                </ContentCard>
            </PageContainer>
        </>
    );
}

BusinessUpgradeRequestsIndex.layout = {
    title: 'Solicitudes',
    breadcrumbs: [
        {
            title: 'Solicitudes',
            href: business.upgradeRequests.index(),
        },
    ],
};
