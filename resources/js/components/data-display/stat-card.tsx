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
    value: string;
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
        <Card className={cn('gap-4 border-[#E2E8F0] bg-white py-5 text-[#0F172A] shadow-sm', className)}>
            <CardHeader className="flex flex-row items-start justify-between gap-4 px-5">
                <div className="space-y-2">
                    <CardDescription className="text-sm font-medium text-[#64748B]">
                        {title}
                    </CardDescription>
                    <CardTitle className="text-3xl font-semibold tracking-tight text-[#0F172A]">
                        {value}
                    </CardTitle>
                </div>
                {icon ? (
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#FFF4E8] text-[#FF7A00] [&_svg]:size-5">
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
                        <p className="text-xs text-[#64748B]">
                            {trend?.label ?? description}
                        </p>
                    ) : null}
                </CardContent>
            )}
        </Card>
    );
}
