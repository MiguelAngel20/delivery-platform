import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { StatusBadge } from '@/components/data-display/status-badge';
import { FormField } from '@/components/forms/form-field';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { destroy, store, update } from '@/routes/admin/business-types';

type BusinessTypeRow = {
    id: number;
    name: string;
    description: string | null;
    status: string;
    status_label: string;
    sort_order: number;
    can_delete: boolean;
};

type Props = {
    types: BusinessTypeRow[];
    options: {
        statuses: Array<{ value: string; label: string }>;
    };
};

export default function AdminBusinessTypesIndex({ types, options }: Props) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<BusinessTypeRow | null>(null);
    const form = useForm({
        name: '',
        description: '',
        status: 'active',
    });

    const openCreate = () => {
        setEditing(null);
        form.clearErrors();
        form.setData({
            name: '',
            description: '',
            status: 'active',
        });
        setOpen(true);
    };

    const openEdit = (type: BusinessTypeRow) => {
        setEditing(type);
        form.clearErrors();
        form.setData({
            name: type.name,
            description: type.description ?? '',
            status: type.status,
        });
        setOpen(true);
    };

    const deleteType = (type: BusinessTypeRow) => {
        if (!type.can_delete) {
            return;
        }

        if (
            !window.confirm(
                `¿Eliminar el tipo / giro "${type.name}"? Esta acción no se puede deshacer.`,
            )
        ) {
            return;
        }

        router.delete(destroy.url(type.id), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Tipos / giros" />
            <PageContainer>
                <PageHeader
                    title="Tipos / giros"
                    description="Catálogo usado al registrar empresas y filtrar en la tienda."
                    actions={
                        <Button
                            type="button"
                            className="gap-1.5"
                            onClick={openCreate}
                        >
                            <Plus className="size-4" aria-hidden />
                            Agregar
                        </Button>
                    }
                />

                <ContentCard className="overflow-hidden p-0">
                    <div className="divide-y divide-border">
                        {types.length === 0 ? (
                            <p className="px-4 py-8 text-center text-sm text-muted-foreground">
                                Aún no hay tipos / giros. Agrega el primero.
                            </p>
                        ) : (
                            types.map((type) => (
                                <div
                                    key={type.id}
                                    className="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-start sm:justify-between"
                                >
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-semibold text-navy">
                                                {type.name}
                                            </p>
                                            <StatusBadge
                                                tone={
                                                    type.status === 'active'
                                                        ? 'success'
                                                        : 'neutral'
                                                }
                                            >
                                                {type.status_label}
                                            </StatusBadge>
                                        </div>
                                        {type.description ? (
                                            <p className="text-sm text-muted-foreground">
                                                {type.description}
                                            </p>
                                        ) : null}
                                        {!type.can_delete ? (
                                            <p className="text-xs text-muted-foreground">
                                                En uso por una o más empresas
                                            </p>
                                        ) : null}
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => openEdit(type)}
                                        >
                                            Editar
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-9 text-muted-foreground hover:text-destructive disabled:opacity-40"
                                            aria-label={`Eliminar ${type.name}`}
                                            title={
                                                type.can_delete
                                                    ? 'Eliminar'
                                                    : 'No se puede eliminar: está en uso'
                                            }
                                            disabled={!type.can_delete}
                                            onClick={() => deleteType(type)}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </ContentCard>
            </PageContainer>

            <Dialog
                open={open}
                onOpenChange={(next) => {
                    setOpen(next);
                    if (!next) {
                        form.clearErrors();
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {editing
                                ? 'Editar tipo / giro'
                                : 'Nuevo tipo / giro'}
                        </DialogTitle>
                    </DialogHeader>
                    <form
                        className="space-y-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            const submitOptions = {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setOpen(false);
                                    form.reset();
                                },
                            };

                            if (editing) {
                                form.put(update.url(editing.id), submitOptions);
                            } else {
                                form.post(store.url(), submitOptions);
                            }
                        }}
                    >
                        <FormField
                            label="Tipo / giro"
                            required
                            error={form.errors.name}
                        >
                            <Input
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                placeholder="Restaurante"
                            />
                        </FormField>
                        <FormField
                            label="Descripción"
                            error={form.errors.description}
                        >
                            <Textarea
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                rows={3}
                                placeholder="Opcional"
                            />
                        </FormField>
                        <FormField
                            label="Estado"
                            required
                            htmlFor="status"
                            error={form.errors.status}
                        >
                            <select
                                id="status"
                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                value={form.data.status}
                                onChange={(event) =>
                                    form.setData('status', event.target.value)
                                }
                            >
                                {options.statuses.map((status) => (
                                    <option
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                        <Button
                            type="submit"
                            className="min-h-11 w-full"
                            disabled={form.processing}
                        >
                            {editing ? 'Guardar cambios' : 'Crear'}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
