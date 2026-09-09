import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { FormField } from '@/components/forms/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { home } from '@/routes/driver';

type DriverDateRangeFilterProps = {
    filters: {
        from: string;
        to: string;
    };
    className?: string;
};

export function DriverDateRangeFilter({
    filters,
    className,
}: DriverDateRangeFilterProps) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [filtering, setFiltering] = useState(false);

    useEffect(() => {
        setFrom(filters.from);
        setTo(filters.to);
    }, [filters.from, filters.to]);

    const applyFilters = () => {
        setFiltering(true);
        router.get(
            home.url({
                query: { from, to },
            }),
            {},
            {
                preserveState: true,
                replace: true,
                onFinish: () => setFiltering(false),
            },
        );
    };

    return (
        <div className={className}>
            <div className="grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                <FormField label="Desde">
                    <Input
                        type="date"
                        value={from}
                        onChange={(event) => setFrom(event.target.value)}
                    />
                </FormField>
                <FormField label="Hasta">
                    <Input
                        type="date"
                        value={to}
                        onChange={(event) => setTo(event.target.value)}
                    />
                </FormField>
                <div className="flex items-end">
                    <Button
                        type="button"
                        className="min-h-12 w-full"
                        loading={filtering}
                        onClick={applyFilters}
                    >
                        Filtrar
                    </Button>
                </div>
            </div>
        </div>
    );
}
