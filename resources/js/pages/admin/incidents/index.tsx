import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { DataTable } from '@/components/data-display/data-table';
import type { DataTableColumn } from '@/components/data-display/data-table';
import { StatusBadge } from '@/components/data-display/status-badge';
import type { StatusTone } from '@/components/data-display/status-badge';
import { FilterSelect } from '@/components/forms/filter-select';
import { FormField } from '@/components/forms/form-field';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAdminOrderEvents } from '@/hooks/realtime/use-order-realtime';
import admin from '@/routes/admin';
import { index as incidentsIndex, show as incidentShow } from '@/routes/admin/incidents';

type Option = { value: string; label: string };

type IncidentRow = {
    id: number;
    order_number: string | null;
    type_label: string;
    reported_by: string | null;
    severity: string;
    severity_label: string;
    status: string;
    status_label: string;
    business_name?: string | null;
    created_at: string | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
};

type Props = {
    incidents: Paginated<IncidentRow>;
    filters: {
        status: string;
        type: string;
        severity: string;
        business_id: string;
        from: string;
        to: string;
    };
    filterOptions: {
        statuses: Option[];
        types: Option[];
        severities: Option[];
        businesses: Option[];
    };
};

const severityTone: Record<string, StatusTone> = {
    low: 'neutral',
    medium: 'info',
    high: 'warning',
    critical: 'danger',
};

const statusTone: Record<string, StatusTone> = {
    open: 'warning',
    under_review: 'info',
    resolved: 'success',
    closed: 'neutral',
};

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('es-MX', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

const columns: DataTableColumn<IncidentRow>[] = [
    {
        key: 'id',
        header: 'Incidencia',
        cell: (row) => `#${row.id}`,
    },
    {
        key: 'order_number',
        header: 'Pedido',
        cell: (row) => (row.order_number ? `#${row.order_number}` : '—'),
    },
    {
        key: 'type_label',
        header: 'Tipo',
        cell: (row) => row.type_label,
    },
    {
        key: 'reported_by',
        header: 'Reportado por',
        cell: (row) => row.reported_by ?? '—',
    },
    {
        key: 'severity',
        header: 'Severidad',
        cell: (row) => (
            <StatusBadge tone={severityTone[row.severity] ?? 'neutral'}>
                {row.severity_label}
            </StatusBadge>
        ),
    },
    {
        key: 'status',
        header: 'Estado',
        cell: (row) => (
            <StatusBadge tone={statusTone[row.status] ?? 'neutral'}>
                {row.status_label}
            </StatusBadge>
        ),
    },
    {
        key: 'created_at',
        header: 'Fecha',
        cell: (row) => formatDateTime(row.created_at),
    },
    {
        key: 'actions',
        header: 'Acciones',
        className: 'text-right',
        cell: (row) => (
            <Button variant="ghost" size="sm" asChild>
                <Link href={incidentShow.url(row.id)}>Ver</Link>
            </Button>
        ),
    },
];

export default function AdminIncidentsIndex({
    incidents,
    filters,
    filterOptions,
}: Props) {
    const [local, setLocal] = useState(filters);

    useAdminOrderEvents(true, ['incidents']);

    function applyFilters(page?: number) {
        router.get(
            incidentsIndex.url({
                query: {
                    status: local.status || undefined,
                    type: local.type || undefined,
                    severity: local.severity || undefined,
                    business_id: local.business_id || undefined,
                    from: local.from || undefined,
                    to: local.to || undefined,
                    page: page && page > 1 ? page : undefined,
                },
            }),
            {},
            { preserveState: true },
        );
    }

    return (
        <>
            <Head title="Incidencias" />
            <PageContainer>
                <PageHeader
                    title="Incidencias"
                    description="Reportes y cancelaciones que requieren revisión"
                />

                <div className="mb-4 grid gap-3 rounded-xl border border-border bg-white p-4 md:grid-cols-3 lg:grid-cols-7">
                    <FilterSelect
                        label="Estado"
                        value={local.status}
                        onChange={(event) =>
                            setLocal((prev) => ({
                                ...prev,
                                status: event.target.value,
                            }))
                        }
                    >
                        <option value="">Todos</option>
                        {filterOptions.statuses.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </FilterSelect>
                    <FilterSelect
                        label="Tipo"
                        value={local.type}
                        onChange={(event) =>
                            setLocal((prev) => ({
                                ...prev,
                                type: event.target.value,
                            }))
                        }
                    >
                        <option value="">Todos</option>
                        {filterOptions.types.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </FilterSelect>
                    <FilterSelect
                        label="Severidad"
                        value={local.severity}
                        onChange={(event) =>
                            setLocal((prev) => ({
                                ...prev,
                                severity: event.target.value,
                            }))
                        }
                    >
                        <option value="">Todas</option>
                        {filterOptions.severities.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </FilterSelect>
                    <FilterSelect
                        label="Empresa"
                        value={local.business_id}
                        onChange={(event) =>
                            setLocal((prev) => ({
                                ...prev,
                                business_id: event.target.value,
                            }))
                        }
                    >
                        <option value="">Todas</option>
                        {filterOptions.businesses.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </FilterSelect>
                    <FormField label="Desde">
                        <Input
                            type="date"
                            value={local.from}
                            onChange={(event) =>
                                setLocal((prev) => ({
                                    ...prev,
                                    from: event.target.value,
                                }))
                            }
                        />
                    </FormField>
                    <FormField label="Hasta">
                        <Input
                            type="date"
                            value={local.to}
                            onChange={(event) =>
                                setLocal((prev) => ({
                                    ...prev,
                                    to: event.target.value,
                                }))
                            }
                        />
                    </FormField>
                    <div className="flex items-end">
                        <Button type="button" onClick={() => applyFilters()}>
                            Filtrar
                        </Button>
                    </div>
                </div>

                <DataTable
                    columns={columns}
                    data={incidents.data}
                    rowKey={(row) => row.id}
                    pagination={{
                        page: incidents.current_page,
                        lastPage: incidents.last_page,
                        onPageChange: (page) => applyFilters(page),
                    }}
                    emptyTitle="Sin incidencias"
                    emptyDescription="No hay reportes con los filtros actuales."
                />
            </PageContainer>
        </>
    );
}

AdminIncidentsIndex.layout = {
    title: 'Incidencias',
    breadcrumbs: [
        { title: 'Admin', href: admin.home() },
        { title: 'Incidencias', href: incidentsIndex.url() },
    ],
};
