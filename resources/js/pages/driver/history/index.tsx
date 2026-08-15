import { Head } from '@inertiajs/react';
import type { MockHistoryOrder } from '@/apps/driver/mocks';
import { mockHistoryOrders } from '@/apps/driver/mocks';
import {
    DataTable
    
} from '@/components/data-display/data-table';
import type {DataTableColumn} from '@/components/data-display/data-table';
import { StatusBadge } from '@/components/data-display/status-badge';
import type { StatusTone } from '@/components/data-display/status-badge';
import { PageContainer } from '@/components/layout/page';

const statusTone: Record<MockHistoryOrder['status'], StatusTone> = {
    Entregado: 'success',
    Cancelado: 'danger',
};

const columns: DataTableColumn<MockHistoryOrder>[] = [
    {
        key: 'code',
        header: 'Pedido',
        cell: (row) => row.code,
    },
    {
        key: 'business',
        header: 'Empresa',
        cell: (row) => row.business,
    },
    {
        key: 'date',
        header: 'Fecha',
        cell: (row) => row.date,
    },
    {
        key: 'earnings',
        header: 'Ganancia',
        cell: (row) => row.earnings,
    },
    {
        key: 'status',
        header: 'Estado',
        cell: (row) => (
            <StatusBadge tone={statusTone[row.status]}>
                {row.status}
            </StatusBadge>
        ),
    },
];

export default function DriverHistoryIndex() {
    return (
        <>
            <Head title="Historial" />
            <PageContainer className="gap-4 px-4 py-4 md:px-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight text-navy">
                        Historial
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Pedidos recientes
                    </p>
                </div>

                <div className="flex flex-col gap-3 md:hidden">
                    {mockHistoryOrders.map((order) => (
                        <article
                            key={order.id}
                            className="rounded-xl border border-border bg-surface p-4 shadow-sm"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <h3 className="font-semibold text-navy">
                                        #{order.code}
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        {order.business}
                                    </p>
                                </div>
                                <StatusBadge tone={statusTone[order.status]}>
                                    {order.status}
                                </StatusBadge>
                            </div>
                            <div className="mt-3 flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">
                                    {order.date}
                                </span>
                                <span className="font-semibold text-navy">
                                    {order.earnings}
                                </span>
                            </div>
                        </article>
                    ))}
                </div>

                <div className="hidden md:block">
                    <DataTable
                        columns={columns}
                        data={mockHistoryOrders}
                        rowKey={(row) => row.id}
                    />
                </div>
            </PageContainer>
        </>
    );
}
