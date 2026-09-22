import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { FormField } from '@/components/forms/form-field';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { home } from '@/routes/driver';

type DriverDateRangeFilterProps = {
    filters: {
        from: string;
        to: string;
    };
    /** Destination URL builder; defaults to driver home. */
    actionUrl?: (query: { from?: string; to?: string }) => string;
    className?: string;
};

export function DriverDateRangeFilter({
    filters,
    actionUrl = (query) => home.url({ query }),
    className,
}: DriverDateRangeFilterProps) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const skipNextSync = useRef(false);

    useEffect(() => {
        if (skipNextSync.current) {
            skipNextSync.current = false;
            return;
        }

        setFrom(filters.from);
        setTo(filters.to);
    }, [filters.from, filters.to]);

    const visitRange = (nextFrom: string, nextTo: string) => {
        if (nextFrom.trim() === '' || nextTo.trim() === '') {
            return;
        }

        if (nextFrom === filters.from && nextTo === filters.to) {
            return;
        }

        skipNextSync.current = true;
        router.get(
            actionUrl({
                from: nextFrom,
                to: nextTo,
            }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    return (
        <div className={cn(className)}>
            <div className="grid grid-cols-2 gap-2">
                <FormField
                    label="Desde"
                    className="gap-1 [&_label]:text-[11px] sm:[&_label]:text-xs"
                >
                    <Input
                        type="date"
                        value={from}
                        className="h-9 px-2 text-xs sm:h-10 sm:px-3 sm:text-sm"
                        onChange={(event) => {
                            const nextFrom = event.target.value;
                            setFrom(nextFrom);
                            visitRange(nextFrom, to);
                        }}
                    />
                </FormField>
                <FormField
                    label="Hasta"
                    className="gap-1 [&_label]:text-[11px] sm:[&_label]:text-xs"
                >
                    <Input
                        type="date"
                        value={to}
                        className="h-9 px-2 text-xs sm:h-10 sm:px-3 sm:text-sm"
                        onChange={(event) => {
                            const nextTo = event.target.value;
                            setTo(nextTo);
                            visitRange(from, nextTo);
                        }}
                    />
                </FormField>
            </div>
        </div>
    );
}
