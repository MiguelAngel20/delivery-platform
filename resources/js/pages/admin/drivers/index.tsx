import { Head, Link, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { DataTable } from '@/components/data-display/data-table';
import type { DataTableColumn } from '@/components/data-display/data-table';
import { StatusBadge } from '@/components/data-display/status-badge';
import { FilterSelect } from '@/components/forms/filter-select';
import { FormField } from '@/components/forms/form-field';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useAdminOrderEvents } from '@/hooks/realtime/use-order-realtime';
import admin from '@/routes/admin';
import { destroy, index, show, store, update } from '@/routes/admin/drivers';

type DriverRow = {
    id: number;
    name: string | null;
    first_name: string | null;
    last_name: string | null;
    email: string | null;
    phone: string | null;
    email_verified: boolean;
    role_label: string;
    pays_commission: boolean;
    commission_per_order: string;
    commission_owed?: string;
    completed_orders: number;
    accepted_orders: number;
    cancelled_orders: number;
    average_rating: string | number | null;
    total_ratings: number;
    trust_score: string | number | null;
    quality_label?: string | null;
    requires_review: boolean;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
};

type Props = {
    drivers: Paginated<DriverRow>;
    filters: {
        search: string;
        requires_review: string;
    };
};

const emptyForm = {
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    pays_commission: false,
    commission_per_order: '0',
};

function visitFilters(next: Props['filters'] & { page?: number }) {
    router.get(
        index.url({
            query: {
                search: next.search || undefined,
                requires_review: next.requires_review || undefined,
                page: next.page,
            },
        }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

export default function AdminDriversIndex({ drivers, filters }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<DriverRow | null>(null);
    const form = useForm({ ...emptyForm });

    useAdminOrderEvents(true, ['drivers']);

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            if (search === filters.search) {
                return;
            }

            visitFilters({
                ...filters,
                search,
            });
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [search, filters]);

    const openCreate = () => {
        setEditing(null);
        form.clearErrors();
        form.setData({ ...emptyForm });
        setOpen(true);
    };

    const openEdit = (driver: DriverRow) => {
        setEditing(driver);
        form.clearErrors();
        form.setData({
            first_name: driver.first_name ?? '',
            last_name: driver.last_name ?? '',
            email: driver.email ?? '',
            phone: driver.phone ?? '',
            pays_commission: driver.pays_commission,
            commission_per_order: driver.commission_per_order ?? '0',
        });
        setOpen(true);
    };

    const deleteDriver = (driver: DriverRow) => {
        if (
            !window.confirm(
                `¿Eliminar al repartidor "${driver.name ?? driver.email}"? Esta acción no se puede deshacer.`,
            )
        ) {
            return;
        }

        router.delete(destroy.url(driver.id), { preserveScroll: true });
    };

    const columns: DataTableColumn<DriverRow>[] = [
        {
            key: 'name',
            header: 'Repartidor',
            cell: (row) => (
                <div>
                    <p className="font-medium text-navy">{row.name ?? '—'}</p>
                    <p className="text-xs text-muted-foreground">{row.email}</p>
                    <p className="text-xs text-muted-foreground">{row.phone}</p>
                </div>
            ),
        },
        {
            key: 'email_verified',
            header: 'Correo',
            cell: (row) =>
                row.email_verified ? (
                    <StatusBadge tone="success">Verificado</StatusBadge>
                ) : (
                    <StatusBadge tone="warning">Pendiente</StatusBadge>
                ),
        },
        {
            key: 'commission',
            header: 'Comisión',
            cell: (row) =>
                row.pays_commission ? (
                    <div className="text-sm">
                        <p>${row.commission_per_order}/pedido</p>
                        {Number(row.commission_owed ?? 0) > 0 ? (
                            <p className="text-xs text-amber-700">
                                Debe ${row.commission_owed}
                            </p>
                        ) : (
                            <p className="text-xs text-muted-foreground">
                                Al día
                            </p>
                        )}
                    </div>
                ) : (
                    <span className="text-sm text-muted-foreground">No paga</span>
                ),
        },
        {
            key: 'completed_orders',
            header: 'Completados',
            cell: (row) => row.completed_orders,
        },
        {
            key: 'average_rating',
            header: 'Rating',
            cell: (row) =>
                row.average_rating
                    ? `${row.average_rating} ★ (${row.total_ratings})`
                    : 'Sin calificaciones',
        },
        {
            key: 'trust_score',
            header: 'Trust Score',
            cell: (row) => row.trust_score ?? '—',
        },
        {
            key: 'review',
            header: 'Revisión',
            cell: (row) =>
                row.requires_review ? (
                    <StatusBadge tone="warning">Requires Review</StatusBadge>
                ) : (
                    <StatusBadge tone="success">
                        {row.quality_label ?? 'OK'}
                    </StatusBadge>
                ),
        },
        {
            key: 'actions',
            header: 'Acciones',
            className: 'text-right',
            cell: (row) => (
                <div className="flex items-center justify-end gap-1">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={show.url(row.id)}>Ver</Link>
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-9"
                        aria-label={`Editar ${row.name ?? 'repartidor'}`}
                        onClick={() => openEdit(row)}
                    >
                        <Pencil className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-9 text-muted-foreground hover:text-destructive"
                        aria-label={`Eliminar ${row.name ?? 'repartidor'}`}
                        onClick={() => deleteDriver(row)}
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Repartidores" />
            <PageContainer>
                <PageHeader
                    title="Repartidores"
                    description="Alta de repartidores de plataforma. Deben verificar su correo antes de iniciar sesión."
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
                <DataTable
                    columns={columns}
                    data={drivers.data}
                    rowKey={(row) => row.id}
                    search={{
                        value: search,
                        onChange: setSearch,
                        placeholder: 'Buscar por nombre, correo o teléfono',
                    }}
                    filters={
                        <FilterSelect
                            label="Revisión"
                            value={filters.requires_review || ''}
                            onChange={(event) =>
                                visitFilters({
                                    ...filters,
                                    search,
                                    requires_review: event.target.value,
                                })
                            }
                        >
                            <option value="">Todos</option>
                            <option value="1">Requires Review</option>
                        </FilterSelect>
                    }
                    pagination={{
                        page: drivers.current_page,
                        lastPage: drivers.last_page,
                        onPageChange: (page) =>
                            visitFilters({ ...filters, search, page }),
                    }}
                />
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
                                ? 'Editar repartidor'
                                : 'Agregar repartidor'}
                        </DialogTitle>
                    </DialogHeader>
                    <form
                        className="space-y-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            const options = {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setOpen(false);
                                    form.reset();
                                    setEditing(null);
                                },
                            };

                            if (editing) {
                                form.put(update.url(editing.id), options);
                            } else {
                                form.post(store.url(), options);
                            }
                        }}
                    >
                        <FormField
                            label="Nombre"
                            required
                            error={form.errors.first_name}
                        >
                            <Input
                                value={form.data.first_name}
                                onChange={(event) =>
                                    form.setData(
                                        'first_name',
                                        event.target.value,
                                    )
                                }
                                autoComplete="given-name"
                            />
                        </FormField>
                        <FormField
                            label="Apellido"
                            required
                            error={form.errors.last_name}
                        >
                            <Input
                                value={form.data.last_name}
                                onChange={(event) =>
                                    form.setData(
                                        'last_name',
                                        event.target.value,
                                    )
                                }
                                autoComplete="family-name"
                            />
                        </FormField>
                        <FormField
                            label="Teléfono"
                            required
                            error={form.errors.phone}
                        >
                            <Input
                                value={form.data.phone}
                                onChange={(event) =>
                                    form.setData('phone', event.target.value)
                                }
                                autoComplete="tel"
                            />
                        </FormField>
                        <FormField
                            label="Correo"
                            required
                            error={form.errors.email}
                        >
                            <Input
                                type="email"
                                value={form.data.email}
                                onChange={(event) =>
                                    form.setData('email', event.target.value)
                                }
                                autoComplete="email"
                            />
                        </FormField>
                        <FormField label="Rol">
                            <Input value="Repartidor" disabled readOnly />
                        </FormField>
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.pays_commission}
                                onChange={(event) =>
                                    form.setData(
                                        'pays_commission',
                                        event.target.checked,
                                    )
                                }
                            />
                            Paga comisión a ChisDrive
                        </label>
                        {form.data.pays_commission ? (
                            <FormField
                                label="Comisión por pedido ($0–$10)"
                                required
                                error={form.errors.commission_per_order}
                            >
                                <Input
                                    type="number"
                                    min={0}
                                    max={10}
                                    step="1"
                                    value={form.data.commission_per_order}
                                    onChange={(event) =>
                                        form.setData(
                                            'commission_per_order',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        ) : null}
                        {!editing ? (
                            <p className="text-xs text-muted-foreground">
                                Se enviará un correo con un enlace para verificar
                                la cuenta. Sin verificar no podrá iniciar
                                sesión.
                            </p>
                        ) : null}
                        <Button
                            type="submit"
                            className="min-h-11 w-full"
                            disabled={form.processing}
                        >
                            {editing ? 'Guardar cambios' : 'Crear repartidor'}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

AdminDriversIndex.layout = {
    title: 'Repartidores',
    breadcrumbs: [
        {
            title: 'Repartidores',
            href: admin.drivers.index(),
        },
    ],
};
