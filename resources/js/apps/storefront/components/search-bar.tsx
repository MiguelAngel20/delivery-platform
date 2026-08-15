import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { search as searchRoute } from '@/routes';

type SearchBarProps = {
    defaultValue?: string;
    className?: string;
    autoFocus?: boolean;
};

export function SearchBar({
    defaultValue = '',
    className,
    autoFocus = false,
}: SearchBarProps) {
    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const formData = new FormData(event.currentTarget);
        const q = String(formData.get('q') ?? '').trim();

        router.get(
            searchRoute.url({ query: q ? { q } : {} }),
            {},
            { preserveState: true },
        );
    };

    return (
        <form
            onSubmit={onSubmit}
            className={cn('relative w-full', className)}
            role="search"
        >
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                name="q"
                defaultValue={defaultValue}
                autoFocus={autoFocus}
                placeholder="Buscar restaurantes o comida"
                className="h-12 rounded-xl border-border bg-surface pl-10"
            />
        </form>
    );
}
