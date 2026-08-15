import { Search } from 'lucide-react';
import type { ComponentProps } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type SearchInputProps = ComponentProps<typeof Input> & {
    label?: string;
};

export function SearchInput({
    className,
    label = 'Buscar',
    id = 'search',
    ...props
}: SearchInputProps) {
    return (
        <div className="relative">
            <label htmlFor={id} className="sr-only">
                {label}
            </label>
            <Search
                aria-hidden="true"
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                id={id}
                type="search"
                className={cn('ps-9', className)}
                {...props}
            />
        </div>
    );
}
