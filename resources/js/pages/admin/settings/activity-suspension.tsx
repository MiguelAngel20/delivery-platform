import { Head, Link, useForm } from '@inertiajs/react';
import { FormField } from '@/components/forms/form-field';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';

type ReasonOption = {
    value: string;
    label: string;
    title: string;
    body: string;
    footnote: string;
    active_order_message: string;
    image_url: string;
};

type Props = {
    suspension: {
        is_active: boolean;
        reason: string;
        updated_at: string | null;
    };
    reasons: ReasonOption[];
};

export default function AdminActivitySuspension({
    suspension,
    reasons,
}: Props) {
    const form = useForm({
        is_active: suspension.is_active,
        reason: suspension.reason,
    });

    const selected =
        reasons.find((reason) => reason.value === form.data.reason) ??
        reasons[0];

    return (
        <>
            <Head title="Suspender actividad" />
            <PageContainer className="gap-5 px-4 py-4 md:px-6">
                <PageHeader
                    title="Suspender actividad"
                    description="Cuando está activo, los clientes pueden explorar la app pero no crear pedidos nuevos. El aviso se muestra en cada visita o recarga."
                />

                <ContentCard title="Estado">
                    <form
                        className="space-y-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.put(
                                '/admin/settings/activity-suspension',
                                { preserveScroll: true },
                            );
                        }}
                    >
                        <div className="flex items-center justify-between gap-4 rounded-xl border border-border bg-surface px-4 py-3">
                            <div>
                                <p className="font-medium text-navy">
                                    Suspender pedidos nuevos
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Los pedidos ya aceptados siguen su curso.
                                </p>
                            </div>
                            <Switch
                                checked={form.data.is_active}
                                onCheckedChange={(checked) =>
                                    form.setData('is_active', checked)
                                }
                            />
                        </div>
                        {form.errors.is_active ? (
                            <p className="text-sm text-destructive">
                                {form.errors.is_active}
                            </p>
                        ) : null}

                        <FormField
                            label="Motivo"
                            required
                            error={form.errors.reason}
                        >
                            <div className="grid gap-3">
                                {reasons.map((reason) => (
                                    <label
                                        key={reason.value}
                                        className={cn(
                                            'cursor-pointer rounded-xl border p-4 transition-colors',
                                            form.data.reason === reason.value
                                                ? 'border-primary bg-primary/5'
                                                : 'border-border hover:border-primary/40',
                                        )}
                                    >
                                        <div className="flex items-start gap-3">
                                            <input
                                                type="radio"
                                                name="reason"
                                                className="mt-1"
                                                checked={
                                                    form.data.reason ===
                                                    reason.value
                                                }
                                                onChange={() =>
                                                    form.setData(
                                                        'reason',
                                                        reason.value,
                                                    )
                                                }
                                            />
                                            <div className="min-w-0 space-y-1">
                                                <p className="font-medium text-navy">
                                                    {reason.label}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {reason.title}
                                                </p>
                                            </div>
                                        </div>
                                    </label>
                                ))}
                            </div>
                        </FormField>

                        {selected ? (
                            <div className="rounded-xl border border-dashed border-border bg-muted/30 p-4">
                                <Label className="mb-3 block text-muted-foreground">
                                    Vista previa del aviso
                                </Label>
                                <div className="mx-auto max-w-sm space-y-3 rounded-2xl border border-border bg-background p-5 text-center shadow-sm">
                                    <img
                                        src={selected.image_url}
                                        alt=""
                                        className="mx-auto h-28 w-full max-w-[240px] object-contain"
                                    />
                                    <h3 className="text-lg font-semibold text-navy">
                                        {selected.title}
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        {selected.body}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {selected.footnote}
                                    </p>
                                    <p className="rounded-lg bg-amber-50 px-3 py-2 text-left text-xs text-amber-950">
                                        {selected.active_order_message}
                                    </p>
                                    <Button type="button" className="w-full" disabled>
                                        Aceptar
                                    </Button>
                                </div>
                            </div>
                        ) : null}

                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="submit"
                                disabled={form.processing}
                            >
                                Guardar
                            </Button>
                            <Button asChild type="button" variant="outline">
                                <Link href={admin.settings.index()}>
                                    Volver
                                </Link>
                            </Button>
                        </div>
                    </form>
                </ContentCard>
            </PageContainer>
        </>
    );
}

AdminActivitySuspension.layout = {
    title: 'Suspender actividad',
    breadcrumbs: [
        {
            title: 'Configuración',
            href: admin.settings.index(),
        },
        {
            title: 'Suspender actividad',
            href: '/admin/settings/activity-suspension',
        },
    ],
};
