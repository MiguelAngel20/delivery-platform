import { Form, Head, Link } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { StatusBadge } from '@/components/data-display/status-badge';
import { PageContainer } from '@/components/layout/page';
import { BackButton } from '@/components/navigation/back-button';
import { Button } from '@/components/ui/button';
import { useCustomerCustomOrderEvents } from '@/hooks/realtime/use-order-realtime';
import { formatMoney } from '@/lib/money';
import { accept, cancel, index, reject } from '@/routes/customer/custom-orders';
import { show as orderShow } from '@/routes/customer/orders';

type RequestDetail = {
    id: number;
    status: string;
    status_label: string;
    establishment_name: string | null;
    description: string;
    customer_notes: string | null;
    quoted_order_number: string | null;
    delivery?: { address_text: string } | null;
    latest_quote?: {
        status: string;
        total: string;
        subtotal: string;
        service_fee: string;
        items: Array<{
            description: string;
            quantity: string;
            unit_price: string;
            subtotal: string;
        }>;
    } | null;
};

type Props = {
    request: RequestDetail;
};

export default function CustomerCustomOrderShow({ request }: Props) {
    const { realtime } = usePage().props as {
        realtime?: { customer_id?: number | null };
    };

    useCustomerCustomOrderEvents(realtime?.customer_id);

    return (
        <>
            <Head title={`Solicitud #${request.id}`} />
            <PageContainer className="gap-5 px-4 py-4 md:px-6">
                <div className="flex items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold text-navy">
                        Solicitud #{request.id}
                    </h1>
                    <StatusBadge tone="primary">{request.status_label}</StatusBadge>
                </div>

                <section className="space-y-2 rounded-xl border border-border bg-surface p-4 text-sm">
                    <p>
                        <span className="text-muted-foreground">Establecimiento: </span>
                        {request.establishment_name ?? '—'}
                    </p>
                    <p className="whitespace-pre-wrap">{request.description}</p>
                    {request.customer_notes ? (
                        <p className="text-muted-foreground">Notas: {request.customer_notes}</p>
                    ) : null}
                    <p className="text-muted-foreground">
                        Entrega: {request.delivery?.address_text ?? '—'}
                    </p>
                </section>

                {request.latest_quote && request.status === 'quoted' ? (
                    <section className="space-y-3 rounded-xl border border-primary/30 bg-surface p-4">
                        <h2 className="font-semibold text-navy">
                            Tu cotización está lista
                        </h2>
                        <ul className="space-y-1 text-sm">
                            {request.latest_quote.items.map((item, index) => (
                                <li key={`${item.description}-${index}`} className="flex justify-between">
                                    <span>
                                        {item.quantity} × {item.description}
                                    </span>
                                    <span>{formatMoney(item.subtotal)}</span>
                                </li>
                            ))}
                        </ul>
                        <p className="text-sm text-muted-foreground">
                            Productos {formatMoney(request.latest_quote.subtotal)} · Servicio{' '}
                            {formatMoney(request.latest_quote.service_fee)}
                        </p>
                        <p className="text-xl font-semibold text-navy">
                            {formatMoney(request.latest_quote.total)}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Form {...accept.form(request.id)}>
                                <Button type="submit">Aceptar</Button>
                            </Form>
                            <Form {...reject.form(request.id)}>
                                <Button type="submit" variant="outline">
                                    Rechazar
                                </Button>
                            </Form>
                        </div>
                    </section>
                ) : null}

                {request.quoted_order_number ? (
                    <Button asChild>
                        <Link href={orderShow.url(request.quoted_order_number)}>
                            Ver pedido {request.quoted_order_number}
                        </Link>
                    </Button>
                ) : null}

                {['pending_review', 'reviewing', 'quoted'].includes(request.status) ? (
                    <Form {...cancel.form(request.id)}>
                        <Button type="submit" variant="ghost">
                            Cancelar solicitud
                        </Button>
                    </Form>
                ) : null}

                <BackButton href={index.url()} />
            </PageContainer>
        </>
    );
}
