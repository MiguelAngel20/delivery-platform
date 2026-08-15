import type { MockCustomerOrder } from '@/apps/storefront/mocks';
import { cn } from '@/lib/utils';

type OrderStatusTimelineProps = {
    timeline: MockCustomerOrder['timeline'];
    className?: string;
};

export function OrderStatusTimeline({
    timeline,
    className,
}: OrderStatusTimelineProps) {
    return (
        <ol className={cn('space-y-0', className)}>
            {timeline.map((step, index) => {
                const isLast = index === timeline.length - 1;

                return (
                    <li key={step.key} className="flex gap-3">
                        <div className="flex flex-col items-center">
                            <span
                                className={cn(
                                    'mt-1 size-3 rounded-full border-2',
                                    step.done || step.current
                                        ? 'border-primary bg-primary'
                                        : 'border-border bg-surface',
                                )}
                            />
                            {!isLast ? (
                                <span
                                    className={cn(
                                        'my-1 w-px flex-1',
                                        step.done
                                            ? 'bg-primary'
                                            : 'bg-border',
                                    )}
                                />
                            ) : null}
                        </div>
                        <div className={cn('pb-4', isLast && 'pb-0')}>
                            <p
                                className={cn(
                                    'text-sm font-medium',
                                    step.current
                                        ? 'text-primary'
                                        : step.done
                                          ? 'text-navy'
                                          : 'text-muted-foreground',
                                )}
                            >
                                {step.label}
                            </p>
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
