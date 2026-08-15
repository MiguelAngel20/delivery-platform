import { Head, Link } from '@inertiajs/react';
import { StatusBadge } from '@/components/data-display/status-badge';
import type { StatusTone } from '@/components/data-display/status-badge';
import { PageContainer } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { create, show } from '@/routes/customer/custom-orders';

type RequestRow = {
    id: number;
    status: string;
    status_label: string;
    establishment_name: string | null;
    description: string;
    latest_total: string | null;
};

type Paginated<T> = { data: T[] };

type Props = {
    requests: Paginated<RequestRow>;
};

const statusTone: Record<string, StatusTone> = {
    pending_review: 'warning',
    reviewing: 'info',
    quoted: 'primary',
    converted_to_order: 'success',
    rejected: 'danger',
    cancelled: 'neutral',
};

export default function CustomerCustomOrdersIndex({ requests }: Props) {
    return (
        <>
            <Head title="Pedidos personalizados" />
            <PageContainer className="gap-5 px-4 py-4 md:px-6">
                <div className="flex items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold text-navy">
                        Pedidos personalizados
                    </h1>
                    <Button asChild>
                        <Link href={create.url()}>Nueva solicitud</Link>
                    </Button>
                </div>
                <ul className="space-y-3">
                    {requests.data.map((row) => (
                        <li
                            key={row.id}
                            className="rounded-xl border border-border bg-surface p-4"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="font-medium text-navy">
                                        {row.establishment_name ?? 'Pedido personalizado'}
                                    </p>
                                    <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                                        {row.description}
                                    </p>
                                </div>
                                <StatusBadge tone={statusTone[row.status] ?? 'neutral'}>
                                    {row.status_label}
                                </StatusBadge>
                            </div>
                            <Button variant="ghost" size="sm" className="mt-2 px-0" asChild>
                                <Link href={show.url(row.id)}>Ver</Link>
                            </Button>
                        </li>
                    ))}
                </ul>
            </PageContainer>
        </>
    );
}
