import { useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export type PromotionRecurringHour = {
    day: string;
    day_label?: string;
    is_open: boolean;
    opens_at: string | null;
    closes_at: string | null;
};

export type WeekdayOption = {
    value: string;
    label: string;
};

type PromotionRecurringHoursFieldsProps = {
    weekdays: WeekdayOption[];
    defaultHours: PromotionRecurringHour[];
    value?: PromotionRecurringHour[];
    errors: Record<string, string>;
    fieldName?: string;
    title?: string;
    description?: string;
};

function initialHours(
    weekdays: WeekdayOption[],
    defaultHours: PromotionRecurringHour[],
    value?: PromotionRecurringHour[],
): PromotionRecurringHour[] {
    const source = value?.length ? value : defaultHours;

    return weekdays.map((weekday) => {
        const existing = source.find((row) => row.day === weekday.value);

        return {
            day: weekday.value,
            day_label: weekday.label,
            is_open: existing?.is_open ?? false,
            opens_at: existing?.opens_at ?? '09:00',
            closes_at: existing?.closes_at ?? '12:00',
        };
    });
}

export function PromotionRecurringHoursFields({
    weekdays,
    defaultHours,
    value,
    errors,
    fieldName = 'recurring_hours',
    title = 'Días y horarios de la promoción',
    description = 'Independientes del horario de la sucursal. Marca los días y el rango en que se mostrará (por ejemplo solo 3 horas).',
}: PromotionRecurringHoursFieldsProps) {
    const [hours, setHours] = useState(() =>
        initialHours(weekdays, defaultHours, value),
    );

    const updateDay = (
        day: string,
        patch: Partial<
            Pick<PromotionRecurringHour, 'is_open' | 'opens_at' | 'closes_at'>
        >,
    ) => {
        setHours((current) =>
            current.map((row) =>
                row.day === day
                    ? {
                          ...row,
                          ...patch,
                      }
                    : row,
            ),
        );
    };

    return (
        <div className="space-y-3 sm:col-span-2">
            <input
                type="hidden"
                name={fieldName}
                value={JSON.stringify(
                    hours.map((row) => ({
                        day: row.day,
                        is_open: row.is_open,
                        opens_at: row.is_open ? row.opens_at : null,
                        closes_at: row.is_open ? row.closes_at : null,
                    })),
                )}
            />

            <div>
                <h3 className="text-sm font-medium text-foreground">
                    {title}
                </h3>
                <p className="text-sm text-muted-foreground">{description}</p>
                {errors[fieldName] ? (
                    <p className="mt-1 text-sm text-destructive">
                        {errors[fieldName]}
                    </p>
                ) : null}
            </div>

            <div className="space-y-2 rounded-lg border border-border p-3">
                {hours.map((row, index) => {
                    const dayError =
                        errors[`${fieldName}.${index}.opens_at`] ??
                        errors[`${fieldName}.${index}.closes_at`] ??
                        errors[`${fieldName}.${index}.is_open`];

                    return (
                        <div
                            key={row.day}
                            className={cn(
                                'grid gap-3 rounded-md border border-transparent p-2 sm:grid-cols-[9rem_auto_1fr_1fr]',
                                'items-center',
                                dayError &&
                                    'border-destructive/40 bg-destructive/5',
                            )}
                        >
                            <label className="flex items-center gap-2 text-sm font-medium">
                                <Checkbox
                                    checked={row.is_open}
                                    onCheckedChange={(checked) =>
                                        updateDay(row.day, {
                                            is_open: checked === true,
                                        })
                                    }
                                />
                                <span>{row.day_label ?? row.day}</span>
                            </label>

                            <span className="text-xs text-muted-foreground sm:text-sm">
                                {row.is_open ? 'Activa' : 'Inactiva'}
                            </span>

                            <div className="space-y-1">
                                <Label
                                    htmlFor={`${fieldName}_opens_at_${row.day}`}
                                    className="text-xs text-muted-foreground"
                                >
                                    Desde
                                </Label>
                                <Input
                                    id={`${fieldName}_opens_at_${row.day}`}
                                    type="time"
                                    disabled={!row.is_open}
                                    value={row.opens_at ?? ''}
                                    onChange={(event) =>
                                        updateDay(row.day, {
                                            opens_at:
                                                event.target.value || null,
                                        })
                                    }
                                />
                            </div>

                            <div className="space-y-1">
                                <Label
                                    htmlFor={`${fieldName}_closes_at_${row.day}`}
                                    className="text-xs text-muted-foreground"
                                >
                                    Hasta
                                </Label>
                                <Input
                                    id={`${fieldName}_closes_at_${row.day}`}
                                    type="time"
                                    disabled={!row.is_open}
                                    value={row.closes_at ?? ''}
                                    onChange={(event) =>
                                        updateDay(row.day, {
                                            closes_at:
                                                event.target.value || null,
                                        })
                                    }
                                />
                            </div>

                            {dayError ? (
                                <p className="text-sm text-destructive sm:col-span-4">
                                    {dayError}
                                </p>
                            ) : null}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
