import { cn } from '@/lib/utils';

type LineChartPoint = {
    label: string;
    value: number;
};

type SimpleLineChartProps = {
    data: LineChartPoint[];
    className?: string;
};

export function SimpleLineChart({ data, className }: SimpleLineChartProps) {
    const width = 640;
    const height = 240;
    const padding = { top: 16, right: 16, bottom: 28, left: 12 };
    const max = Math.max(...data.map((point) => point.value), 1);
    const innerWidth = width - padding.left - padding.right;
    const innerHeight = height - padding.top - padding.bottom;

    const points = data.map((point, index) => {
        const x =
            padding.left +
            (data.length === 1 ? innerWidth / 2 : (index / (data.length - 1)) * innerWidth);
        const y =
            padding.top + innerHeight - (point.value / max) * innerHeight;

        return { ...point, x, y };
    });

    const linePath = points
        .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`)
        .join(' ');

    const areaPath = `${linePath} L ${points[points.length - 1]?.x ?? 0} ${
        padding.top + innerHeight
    } L ${points[0]?.x ?? 0} ${padding.top + innerHeight} Z`;

    return (
        <div className={cn('w-full', className)}>
            <svg
                viewBox={`0 0 ${width} ${height}`}
                className="h-56 w-full"
                role="img"
                aria-label="Pedidos últimos 7 días"
            >
                <defs>
                    <linearGradient id="orders-fill" x1="0" y1="0" x2="0" y2="1">
                        <stop
                            offset="0%"
                            stopColor="var(--primary)"
                            stopOpacity="0.25"
                        />
                        <stop
                            offset="100%"
                            stopColor="var(--primary)"
                            stopOpacity="0.02"
                        />
                    </linearGradient>
                </defs>
                {[0.25, 0.5, 0.75, 1].map((ratio) => {
                    const y = padding.top + innerHeight * (1 - ratio);

                    return (
                        <line
                            key={ratio}
                            x1={padding.left}
                            x2={width - padding.right}
                            y1={y}
                            y2={y}
                            stroke="var(--border)"
                            strokeDasharray="4 4"
                        />
                    );
                })}
                <path d={areaPath} fill="url(#orders-fill)" />
                <path
                    d={linePath}
                    fill="none"
                    stroke="var(--primary)"
                    strokeWidth="3"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
                {points.map((point) => (
                    <g key={point.label}>
                        <circle
                            cx={point.x}
                            cy={point.y}
                            r="4"
                            fill="var(--surface)"
                            stroke="var(--primary)"
                            strokeWidth="2"
                        />
                        <text
                            x={point.x}
                            y={height - 8}
                            textAnchor="middle"
                            className="fill-muted-foreground text-[11px]"
                        >
                            {point.label}
                        </text>
                    </g>
                ))}
            </svg>
        </div>
    );
}
