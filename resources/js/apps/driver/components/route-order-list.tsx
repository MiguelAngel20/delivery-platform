import { cn } from '@/lib/utils';

export type DriverRouteGroup = {
    branch_id: number;
    business?: string | null;
    branch_name?: string | null;
    order_numbers: string[];
};

type RouteOrderListProps = {
    route: DriverRouteGroup;
    className?: string;
};

export function RouteOrderList({ route, className }: RouteOrderListProps) {
    return (
        <section
            className={cn(
                'rounded-xl border border-border bg-surface p-4 shadow-sm',
                className,
            )}
        >
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                Mi ruta
            </p>
            <h3 className="mt-1 text-base font-semibold text-navy">
                {route.business}
                {route.branch_name ? ` · ${route.branch_name}` : ''}
            </h3>
            <p className="text-sm text-muted-foreground">
                {route.order_numbers.length} pedidos activos
            </p>
            <ul className="mt-3 space-y-2">
                {route.order_numbers.map((code) => (
                    <li
                        key={code}
                        className="rounded-lg border border-border bg-background px-3 py-2 text-sm font-medium text-navy"
                    >
                        #{code}
                    </li>
                ))}
            </ul>
        </section>
    );
}
