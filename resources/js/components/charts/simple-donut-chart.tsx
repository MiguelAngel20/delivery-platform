import { cn } from '@/lib/utils';

type DonutSlice = {
    label: string;
    value: number;
    color: string;
};

type SimpleDonutChartProps = {
    data: DonutSlice[];
    className?: string;
};

export function SimpleDonutChart({ data, className }: SimpleDonutChartProps) {
    const total = data.reduce((sum, slice) => sum + slice.value, 0) || 1;
    const radius = 56;
    const stroke = 18;
    const circumference = 2 * Math.PI * radius;

    const slices = data.reduce<
        Array<DonutSlice & { length: number; offset: number }>
    >((acc, slice) => {
        const length = (slice.value / total) * circumference;
        const offset = acc.reduce((sum, item) => sum + item.length, 0);

        acc.push({ ...slice, length, offset });

        return acc;
    }, []);

    return (
        <div
            className={cn(
                'flex flex-col items-center gap-6 sm:flex-row sm:items-center',
                className,
            )}
        >
            <svg
                viewBox="0 0 160 160"
                className="size-40 shrink-0"
                role="img"
                aria-label="Pedidos por estado"
            >
                <circle
                    cx="80"
                    cy="80"
                    r={radius}
                    fill="none"
                    stroke="var(--border)"
                    strokeWidth={stroke}
                />
                {slices.map((slice) => (
                    <circle
                        key={slice.label}
                        cx="80"
                        cy="80"
                        r={radius}
                        fill="none"
                        stroke={slice.color}
                        strokeWidth={stroke}
                        strokeDasharray={`${slice.length} ${circumference - slice.length}`}
                        strokeDashoffset={-slice.offset}
                        strokeLinecap="butt"
                        transform="rotate(-90 80 80)"
                    />
                ))}
                <text
                    x="80"
                    y="76"
                    textAnchor="middle"
                    className="fill-navy text-2xl font-semibold"
                >
                    {total}
                </text>
                <text
                    x="80"
                    y="96"
                    textAnchor="middle"
                    className="fill-muted-foreground text-[11px]"
                >
                    Total
                </text>
            </svg>

            <ul className="flex w-full flex-col gap-3">
                {data.map((slice) => {
                    const percent = Math.round((slice.value / total) * 100);

                    return (
                        <li
                            key={slice.label}
                            className="flex items-center justify-between gap-3 text-sm"
                        >
                            <div className="flex min-w-0 items-center gap-2">
                                <span
                                    className="size-2.5 shrink-0 rounded-full"
                                    style={{ backgroundColor: slice.color }}
                                />
                                <span className="truncate text-navy">
                                    {slice.label}
                                </span>
                            </div>
                            <span className="shrink-0 text-muted-foreground">
                                {slice.value} · {percent}%
                            </span>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
