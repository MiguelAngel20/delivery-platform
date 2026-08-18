import { useState } from 'react';
import type { BusinessOpeningHour, EnumOption } from '@/apps/admin/businesses/types';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type OpeningHoursFieldsProps = {
    weekdays: EnumOption[];
    defaultHours: BusinessOpeningHour[];
    value?: BusinessOpeningHour[];
    errors: Record<string, string>;
    idPrefix?: string;
};

function initialHours(
    weekdays: EnumOption[],
    defaultHours: BusinessOpeningHour[],
    value?: BusinessOpeningHour[],
): BusinessOpeningHour[] {
    const source = value?.length ? value : defaultHours;

    return weekdays.map((weekday) => {
        const existing = source.find((row) => row.day === weekday.value);

        return {
            day: weekday.value,
            day_label: weekday.label,
            is_open: existing?.is_open ?? false,
            opens_at: existing?.opens_at ?? '09:00',
            closes_at: existing?.closes_at ?? '21:00',
        };
    });
}

export function OpeningHoursFields({
    weekdays,
    defaultHours,
    value,
    errors,
    idPrefix = 'hours',
}: OpeningHoursFieldsProps) {
    const [openingHours, setOpeningHours] = useState(() =>
        initialHours(weekdays, defaultHours, value),
    );

    const updateDay = (
        day: string,
        patch: Partial<Pick<BusinessOpeningHour, 'is_open' | 'opens_at' | 'closes_at'>>,
    ) => {
        setOpeningHours((current) =>
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
        <div className="space-y-3 md:col-span-2">
            <input
                type="hidden"
                name="opening_hours"
                value={JSON.stringify(
                    openingHours.map((row) => ({
                        day: row.day,
                        is_open: row.is_open,
                        opens_at: row.is_open ? row.opens_at : null,
                        closes_at: row.is_open ? row.closes_at : null,
                    })),
                )}
            />

            <div>
                <h3 className="text-sm font-medium text-foreground">
                    Días y horarios de trabajo
                </h3>
                <p className="text-sm text-muted-foreground">
                    Marca los días abiertos e indica el horario de esta
                    sucursal.
                </p>
                {errors.opening_hours ? (
                    <p className="mt-1 text-sm text-destructive">
                        {errors.opening_hours}
                    </p>
                ) : null}
            </div>

            <div className="space-y-2 rounded-lg border border-border p-3">
                {openingHours.map((row, index) => {
                    const dayError =
                        errors[`opening_hours.${index}.opens_at`] ??
                        errors[`opening_hours.${index}.closes_at`] ??
                        errors[`opening_hours.${index}.is_open`];

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
                                {row.is_open ? 'Abierto' : 'Cerrado'}
                            </span>

                            <div className="space-y-1">
                                <Label
                                    htmlFor={`${idPrefix}_opens_at_${row.day}`}
                                    className="text-xs text-muted-foreground"
                                >
                                    Abre
                                </Label>
                                <Input
                                    id={`${idPrefix}_opens_at_${row.day}`}
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
                                    htmlFor={`${idPrefix}_closes_at_${row.day}`}
                                    className="text-xs text-muted-foreground"
                                >
                                    Cierra
                                </Label>
                                <Input
                                    id={`${idPrefix}_closes_at_${row.day}`}
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
