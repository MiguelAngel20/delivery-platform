import { Head, Link, router } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { EmployeeFormOptions } from '@/apps/business/components/employee-form';
import {
    DataTable
    
} from '@/components/data-display/data-table';
import type {DataTableColumn} from '@/components/data-display/data-table';
import { StatusBadge } from '@/components/data-display/status-badge';
import { FilterSelect } from '@/components/forms/filter-select';
import { PageContainer, PageHeader } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import business from '@/routes/business';
import { create, edit, index } from '@/routes/business/employees';

type EmployeeRow = {
    id: number;
    role: string;
    role_label: string;
    status: string;
    status_label: string;
    user: {
        id: number;
        first_name: string;
        last_name: string;
        name: string;
        email: string;
        phone: string;
    };
    branches: Array<{ id: number; name: string }>;
};

type Filters = {
    search: string;
    role: string;
    status: string;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
};

type Props = {
    employees: Paginated<EmployeeRow>;
    filters: Filters;
    options: EmployeeFormOptions;
    limits: {
        max_employees_per_branch: number;
        can_add_business_admin: boolean;
        branch_employee_usage: Array<{
            branch_id: number;
            branch_name: string;
            used: number;
            max: number;
            remaining: number;
        }>;
    };
};

function statusTone(status: string) {
    return status === 'active' ? 'success' : 'neutral';
}

const columns: DataTableColumn<EmployeeRow>[] = [
    {
        key: 'name',
        header: 'Empleado',
        cell: (row) => row.user.name,
    },
    {
        key: 'email',
        header: 'Correo',
        cell: (row) => row.user.email,
    },
    {
        key: 'phone',
        header: 'Teléfono',
        cell: (row) => row.user.phone ?? '—',
    },
    {
        key: 'role',
        header: 'Rol',
        cell: (row) => row.role_label,
    },
    {
        key: 'branches',
        header: 'Sucursales',
        cell: (row) =>
            row.role === 'business_admin'
                ? 'Todas'
                : row.branches.length > 0
                  ? row.branches.map((branch) => branch.name).join(', ')
                  : '—',
    },
    {
        key: 'status',
        header: 'Estado',
        cell: (row) => (
            <StatusBadge tone={statusTone(row.status)}>
                {row.status_label}
            </StatusBadge>
        ),
    },
    {
        key: 'actions',
        header: 'Acciones',
        className: 'text-right',
        cell: (row) => (
            <Button variant="ghost" size="icon" className="size-8" asChild>
                <Link
                    href={edit.url(row.id)}
                    aria-label={`Editar ${row.user.name}`}
                    title="Editar"
                >
                    <Pencil className="size-4" />
                </Link>
            </Button>
        ),
    },
];

function visitFilters(next: Partial<Filters> & { page?: number }) {
    router.get(
        index.url({
            query: {
                search: next.search || undefined,
                role: next.role || undefined,
                status: next.status || undefined,
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

export default function BusinessEmployeesIndex({
    employees,
    filters,
    options,
    limits,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const hasEmployeeCapacity = limits.branch_employee_usage.some(
        (branch) => branch.remaining > 0,
    );
    const canAdd =
        hasEmployeeCapacity || limits.can_add_business_admin;

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

    return (
        <>
            <Head title="Empleados" />
            <PageContainer>
                <PageHeader
                    title="Empleados"
                    description={
                        limits.branch_employee_usage.length > 0
                            ? limits.branch_employee_usage
                                  .map(
                                      (branch) =>
                                          `${branch.branch_name}: ${branch.used} de ${branch.max}`,
                                  )
                                  .join(' · ')
                            : undefined
                    }
                    actions={
                        canAdd ? (
                            <Button asChild>
                                <Link href={create.url()}>
                                    + Nuevo empleado
                                </Link>
                            </Button>
                        ) : (
                            <Button type="button" disabled>
                                Límite alcanzado
                            </Button>
                        )
                    }
                />
                <DataTable
                    columns={columns}
                    data={employees.data}
                    rowKey={(row) => row.id}
                    search={{
                        value: search,
                        onChange: setSearch,
                        placeholder: 'Buscar por nombre, correo o teléfono',
                    }}
                    filters={
                        <>
                            <FilterSelect
                                label="Rol"
                                value={filters.role || ''}
                                onChange={(event) =>
                                    visitFilters({
                                        ...filters,
                                        search,
                                        role: event.target.value,
                                    })
                                }
                            >
                                <option value="">Todos los roles</option>
                                {options.roles.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </FilterSelect>
                            <FilterSelect
                                label="Estado"
                                value={filters.status || ''}
                                onChange={(event) =>
                                    visitFilters({
                                        ...filters,
                                        search,
                                        status: event.target.value,
                                    })
                                }
                            >
                                <option value="">Todos los estados</option>
                                {options.statuses.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </FilterSelect>
                        </>
                    }
                    pagination={{
                        page: employees.current_page,
                        lastPage: employees.last_page,
                        onPageChange: (page) =>
                            visitFilters({
                                ...filters,
                                search,
                                page,
                            }),
                    }}
                    emptyTitle="Sin empleados"
                    emptyDescription="Agrega el primer empleado de tu empresa."
                />
            </PageContainer>
        </>
    );
}

BusinessEmployeesIndex.layout = {
    title: 'Empleados',
    breadcrumbs: [
        {
            title: 'Empleados',
            href: business.employees.index(),
        },
    ],
};
