import type { ReactNode } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

type StatCardProps = {
    title: string;
    value: string | number;
    description?: string;
    icon?: ReactNode;
    trend?: {
        value: string;
        direction?: 'up' | 'down' | 'neutral';
        label?: string;
    };
    className?: string;
};

export function StatCard({
    title,
    value,
    description,
    icon,
    trend,
    className,
}: StatCardProps) {
    const trendColor =
        trend?.direction === 'down'
            ? 'text-danger'
            : trend?.direction === 'up'
              ? 'text-success'
              : 'text-muted-foreground';

    return (
        <Card
            className={cn(
                'gap-4 border-border bg-card py-5 text-card-foreground shadow-sm',
                className,
            )}
        >
            <CardHeader className="flex flex-row items-start justify-between gap-4 px-5">
                <div className="space-y-2">
                    <CardDescription className="text-sm font-medium text-muted-foreground">
                        {title}
                    </CardDescription>
                    <CardTitle className="text-3xl font-semibold tracking-tight text-foreground">
                        {value}
                    </CardTitle>
                </div>
                {icon ? (
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary [&_svg]:size-5">
                        {icon}
                    </div>
                ) : null}
            </CardHeader>
            {(trend || description) && (
                <CardContent className="space-y-0.5 px-5">
                    {trend ? (
                        <p className={cn('text-sm font-medium', trendColor)}>
                            {trend.direction === 'up'
                                ? '↑ '
                                : trend.direction === 'down'
                                  ? '↓ '
                                  : ''}
                            {trend.value}
                        </p>
                    ) : null}
                    {trend?.label || description ? (
                        <p className="text-xs text-muted-foreground">
                            {trend?.label ?? description}
                        </p>
                    ) : null}
                </CardContent>
            )}
        </Card>
    );
}
