import type { ReactNode } from 'react';
import { EmptyState } from '@/components/feedback/empty-state';
import { SearchInput } from '@/components/forms/search-input';
import { Pagination } from '@/components/ui/pagination';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';

export type DataTableColumn<T> = {
    key: string;
    header: string;
    className?: string;
    cell: (row: T) => ReactNode;
};

type DataTableProps<T> = {
    columns: DataTableColumn<T>[];
    data: T[];
    rowKey: (row: T) => string | number;
    search?: {
        value: string;
        onChange: (value: string) => void;
        placeholder?: string;
    };
    filters?: ReactNode;
    actions?: ReactNode;
    pagination?: {
        page: number;
        lastPage: number;
        onPageChange: (page: number) => void;
    };
    emptyTitle?: string;
    emptyDescription?: string;
    className?: string;
};

export function EmptyTableState({
    title = 'Sin resultados',
    description,
}: {
    title?: string;
    description?: string;
}) {
    return <EmptyState title={title} description={description} />;
}

export function DataTable<T>({
    columns,
    data,
    rowKey,
    search,
    filters,
    actions,
    pagination,
    emptyTitle,
    emptyDescription,
    className,
}: DataTableProps<T>) {
    return (
        <div className={cn('flex flex-col gap-4', className)}>
            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div className="flex flex-1 flex-col gap-3 md:flex-row md:items-center">
                    {search ? (
                        <div className="w-full md:max-w-xs">
                            <SearchInput
                                value={search.value}
                                placeholder={search.placeholder ?? 'Buscar…'}
                                onChange={(event) =>
                                    search.onChange(event.target.value)
                                }
                            />
                        </div>
                    ) : null}
                    {filters}
                </div>
                {actions}
            </div>

            {data.length === 0 ? (
                <EmptyTableState
                    title={emptyTitle}
                    description={emptyDescription}
                />
            ) : (
                <div className="overflow-hidden rounded-xl border bg-surface shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {columns.map((column) => (
                                    <TableHead
                                        key={column.key}
                                        className={column.className}
                                    >
                                        {column.header}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {data.map((row) => (
                                <TableRow key={rowKey(row)}>
                                    {columns.map((column) => (
                                        <TableCell
                                            key={column.key}
                                            className={column.className}
                                        >
                                            {column.cell(row)}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}

            {pagination && data.length > 0 ? (
                <Pagination
                    page={pagination.page}
                    lastPage={pagination.lastPage}
                    onPageChange={pagination.onPageChange}
                />
            ) : null}
        </div>
    );
}
