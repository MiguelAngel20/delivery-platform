import { router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FocusEvent, FormEvent } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { home, search as searchRoute } from '@/routes';

type SearchBarProps = {
    defaultValue?: string;
    className?: string;
    autoFocus?: boolean;
    onDismiss?: () => void;
};

const DEBOUNCE_MS = 250;

function visitSearchQuery(q: string): void {
    const trimmed = q.trim();

    if (trimmed === '') {
        // Clearing search returns to the home / initial state.
        router.get(
            home.url(),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );

        return;
    }

    router.get(
        searchRoute.url({ query: { q: trimmed } }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

export function SearchBar({
    defaultValue = '',
    className,
    autoFocus = false,
    onDismiss,
}: SearchBarProps) {
    const page = usePage();
    const [value, setValue] = useState(defaultValue);
    const skipDebounce = useRef(true);
    const pageRef = useRef(page);
    pageRef.current = page;

    // Only react to typed value changes. If we also depend on page/url, leaving
    // search for a restaurant remounts the debounce and sends the user back to /search.
    useEffect(() => {
        if (skipDebounce.current) {
            skipDebounce.current = false;

            return;
        }

        const timeout = window.setTimeout(() => {
            const next = value.trim();
            const currentPage = pageRef.current;
            const current = String(
                (currentPage.props as { q?: string }).q ?? '',
            ).trim();
            const onSearchPage = currentPage.component === 'public/search/index';

            if (next === '') {
                if (onSearchPage || current !== '') {
                    visitSearchQuery('');
                }

                return;
            }

            if (onSearchPage && next === current) {
                return;
            }

            visitSearchQuery(next);
        }, DEBOUNCE_MS);

        return () => window.clearTimeout(timeout);
    }, [value]);

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        visitSearchQuery(value);
    };

    const onBlur = (event: FocusEvent<HTMLInputElement>) => {
        const next = event.relatedTarget;

        if (
            next instanceof Element &&
            (next.closest('#storefront-search') ||
                next.closest('[aria-controls="storefront-search"]'))
        ) {
            return;
        }

        if (value.trim() === '') {
            if (page.component === 'public/search/index') {
                visitSearchQuery('');
            }

            onDismiss?.();
        }
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
                value={value}
                onChange={(event) => setValue(event.target.value)}
                onBlur={onBlur}
                autoFocus={autoFocus}
                placeholder="Buscar restaurantes, comida, postres..."
                className="h-12 rounded-xl border-border bg-surface pl-10"
                autoComplete="off"
            />
        </form>
    );
}
